<?php

namespace App\Http\Controllers;

use App\Support\RedirectAfterSave;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    use AuthorizesRequests;

    protected function redirectAfterSave(Model $model, string $message): RedirectResponse
    {
        return RedirectAfterSave::to($model, $message);
    }
}
