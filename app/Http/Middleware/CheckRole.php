<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if (!in_array($user->role, $roles)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
