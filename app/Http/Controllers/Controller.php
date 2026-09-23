<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RedirectAfterSave;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    use AuthorizesRequests;

    protected function actor(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }

    protected function redirectAfterSave(Model $model, string $message): RedirectResponse
    {
        return RedirectAfterSave::to($model, $message);
    }
}
