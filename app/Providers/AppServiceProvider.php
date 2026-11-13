<?php

namespace App\Providers;

use App\Models\User;
use App\Support\NightwatchSqlMessage;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Exception as ExceptionRecord;
use Laravel\Nightwatch\Records\Mail as MailRecord;
use Laravel\Nightwatch\Records\Request as RequestRecord;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $this->registerNightwatchRedactions();
        $this->registerCaseInsensitiveLike();
        $this->registerAuthorization();
        $this->registerNumericRouteParameters();

        ResetPassword::toMailUsing(function (object $user, string $token) {
            assert($user instanceof User);

            return (new MailMessage)
                ->subject('Reset your '.config('app.name').' password')
                ->markdown('mail.password-reset', [
                    'firstName' => strtok($user->name, ' '),
                    'url' => route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]),
                    'expires' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]);
        });
    }

    /**
     * Non-numeric ids 404 at the router instead of reaching Postgres as a bigint cast error (500).
     */
    private function registerNumericRouteParameters(): void
    {
        Route::patterns(array_fill_keys([
            'activity', 'activity_type', 'agreement', 'agreementId', 'attachment', 'award',
            'certificate', 'certificateEnrollment', 'certificationCandidate', 'certification_tool', 'contact_family',
            'fieldId', 'logging_field', 'organization', 'program', 'project', 'requirement',
            'state', 'team', 'user',
        ], '[0-9]+'));
    }

    private function registerAuthorization(): void
    {
        // System admin: allow everything except UserPolicy (self-edit / last-admin still apply).
        Gate::before(function (User $user, string $ability, array $arguments) {
            if (! $user->isSystemAdmin()) {
                return null;
            }

            if (isset($arguments[0]) && $arguments[0] instanceof User) {
                return null;
            }

            return true;
        });
    }

    private function registerCaseInsensitiveLike(): void
    {
        $operator = function ($query): string {
            return $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        };

        EloquentBuilder::macro('whereIlike', function (string $column, mixed $value) use ($operator) {
            return $this->where($column, $operator($this), $value);
        });

        EloquentBuilder::macro('orWhereIlike', function (string $column, mixed $value) use ($operator) {
            return $this->orWhere($column, $operator($this), $value);
        });

        QueryBuilder::macro('whereIlike', function (string $column, mixed $value) use ($operator) {
            return $this->where($column, $operator($this), $value);
        });

        QueryBuilder::macro('orWhereIlike', function (string $column, mixed $value) use ($operator) {
            return $this->orWhere($column, $operator($this), $value);
        });
    }

    private function registerNightwatchRedactions(): void
    {
        // 1. Users: capture staff ID only, no name or email
        Nightwatch::user(fn ($user) => []);

        // 2. Strip query strings from URLs and referers (search terms like ?q=Jane+Doe), drop IPs
        Nightwatch::redactRequests(function (RequestRecord $request) {
            $request->url = Str::before($request->url, '?');
            $request->ip = '';

            if ($request->headers->has('referer')) {
                $request->headers->set('referer', Str::before($request->headers->get('referer') ?? '', '?'));
            }
        });

        // 3. DB exception messages embed the full SQL WITH values,
        //    and Postgres adds a DETAIL line like "Key (email)=(jane@...)"
        Nightwatch::redactExceptions(function (ExceptionRecord $exception) {
            if (str_starts_with($exception->message, 'SQLSTATE')) {
                $exception->message = NightwatchSqlMessage::trim($exception->message);
            }
        });

        // 4. Mail subjects (if any mention a client)
        Nightwatch::redactMail(function (MailRecord $mail) {
            $mail->subject = '[redacted]';
        });
    }
}
