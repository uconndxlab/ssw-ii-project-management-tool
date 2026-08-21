<?php

namespace App\Providers;

use App\Services\SessionBackTargetService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\ServiceProvider;

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
        $this->registerCaseInsensitiveLike();

        ResetPassword::createUrlUsing(function (object $user, string $token) {
            return route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }

    /**
     * Postgres LIKE is case-sensitive; SQLite LIKE is not. Use ilike on pgsql.
     */
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
}
