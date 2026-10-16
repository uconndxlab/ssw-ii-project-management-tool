<?php

namespace App\Http\Middleware;

use App\Enums\RecentRecordType;
use App\Services\RecentRecordService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordRecentVisit
{
    public function __construct(private readonly RecentRecordService $recentRecords) {}

    /**
     * Remember successful full-page visits to a record's show page for the Recent panel.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $route = $request->route();
        $type = RecentRecordType::fromRouteName($route?->getName());

        if (
            $user === null
            || $route === null
            || $type === null
            || ! $request->isMethod('GET')
            || $request->ajax()
            || $request->header('HX-Request') === 'true'
            || $response->getStatusCode() !== Response::HTTP_OK
        ) {
            return $response;
        }

        $record = collect($route->parameters())
            ->first(fn ($parameter) => is_a($parameter, $type->modelClass()));

        if ($record instanceof Model) {
            $this->recentRecords->record($user, $type, (int) $record->getKey());
        }

        return $response;
    }
}
