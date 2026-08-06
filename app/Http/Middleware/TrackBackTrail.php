<?php

namespace App\Http\Middleware;

use App\Services\SessionBackTargetService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackBackTrail
{
    public function __construct(private readonly SessionBackTargetService $backTargetService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->backTargetService->track($request);

        return $next($request);
    }
}
