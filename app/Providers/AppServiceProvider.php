<?php

namespace App\Providers;

use App\Models\User;
use App\Services\SessionBackTargetService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Exception as ExceptionRecord;
use Laravel\Nightwatch\Records\Mail as MailRecord;
use Laravel\Nightwatch\Records\Request as RequestRecord;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SessionBackTargetService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $this->registerNightwatchRedactions();
        $this->registerCaseInsensitiveLike();
        $this->registerAuthorization();

        ResetPassword::createUrlUsing(function (object $user, string $token) {
            return route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
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
                $request->headers->set('referer', Str::before($request->headers->get('referer'), '?'));
            }
        });

        // 3. DB exception messages embed the full SQL WITH values,
        //    and Postgres adds a DETAIL line like "Key (email)=(jane@...)"
        Nightwatch::redactExceptions(function (ExceptionRecord $exception) {
            if (str_starts_with($exception->message, 'SQLSTATE')) {
                $exception->message = preg_split(
                    '/\s+DETAIL:|\s+\(Connection:/',
                    $exception->message
                )[0];
            }
        });

        // 4. Mail subjects (if any mention a client)
        Nightwatch::redactMail(function (MailRecord $mail) {
            $mail->subject = '[redacted]';
        });
    }
}
