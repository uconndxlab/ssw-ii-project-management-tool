<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordResetEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filled(config('mail.from.address'))) {
            abort(404);
        }

        return $next($request);
    }
}
