<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RedirectAfterSave
{
    /**
     * After an edit-form save: show if the user can view the record, otherwise
     * index if they can list the resource, otherwise the dashboard.
     */
    public static function to(Model $model, string $message): RedirectResponse
    {
        $user = Auth::user();
        $model->unsetRelations();

        $showRoute = self::showRouteName($model);
        if ($user && $showRoute !== null && $user->can('view', $model)) {
            return redirect()->route($showRoute, $model)->with('success', $message);
        }

        $indexRoute = self::indexRouteName($model);
        if ($user && $indexRoute !== null && $user->can('viewAny', $model::class)) {
            return redirect()->route($indexRoute)->with('success', $message);
        }

        return redirect()->route('dashboard')->with('success', $message);
    }

    private static function showRouteName(Model $model): ?string
    {
        $name = self::resourceName($model).'.show';

        return Route::has($name) ? $name : null;
    }

    private static function indexRouteName(Model $model): ?string
    {
        $name = match ($model::class) {
            User::class => 'admin.users.index',
            default => self::resourceName($model).'.index',
        };

        return Route::has($name) ? $name : null;
    }

    private static function resourceName(Model $model): string
    {
        return Str::plural(Str::kebab(class_basename($model)));
    }
}
