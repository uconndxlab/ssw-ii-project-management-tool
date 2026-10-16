<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SessionBackTargetService
{
    private const SESSION_KEY = 'navigation.back_trail';

    private const MAX_DEPTH = 12;

    private const MAX_AGE_SECONDS = 14400;

    public function clear(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    public function track(Request $request): void
    {
        if (! $this->shouldTrack($request)) {
            return;
        }

        $routeName = $request->route()?->getName();
        $trail = $this->shouldResetOnArrival($routeName)
            ? []
            : $this->pruneTrail($request, $this->trail($request));
        $entry = $this->makeEntry($request);

        if ($entry === null) {
            $this->storeTrail($request, $trail);

            return;
        }

        $existingIndex = $this->indexOfUrl($trail, $entry['url']);

        if ($existingIndex !== null) {
            $this->storeTrail($request, array_slice($trail, 0, $existingIndex + 1));

            return;
        }

        $trail = array_values(array_filter($trail, function (array $candidate) use ($entry) {
            return ($candidate['url'] ?? null) !== $entry['url'];
        }));

        $trail[] = $entry;

        if (count($trail) > self::MAX_DEPTH) {
            $trail = array_slice($trail, -self::MAX_DEPTH);
        }

        $this->storeTrail($request, $trail);
    }

    /**
     * @param  array<int, array<string, mixed>>  $trail
     */
    private function indexOfUrl(array $trail, string $url): ?int
    {
        foreach ($trail as $index => $entry) {
            if (($entry['url'] ?? null) === $url) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{url: string, label: string}|null
     */
    public function resolve(Request $request): ?array
    {
        if (! $request->hasSession() || ! $request->user()) {
            return null;
        }

        $trail = $this->pruneTrail($request, $this->trail($request));
        $currentUrl = $this->normalizeUrl($request->fullUrl());
        $changed = false;

        for ($index = count($trail) - 1; $index >= 0; $index--) {
            $entry = $trail[$index];
            $entryUrl = $entry['url'] ?? null;

            if (! $entryUrl || $entryUrl === $currentUrl) {
                continue;
            }

            if (! $this->isValidEntry($request, $entry)) {
                unset($trail[$index]);
                $changed = true;

                continue;
            }

            if ($changed) {
                $this->storeTrail($request, array_values($trail));
            }

            return [
                'url' => $entryUrl,
                'label' => $this->backLabelForEntry($entry),
            ];
        }

        if ($changed) {
            $this->storeTrail($request, array_values($trail));
        }

        return null;
    }

    private function shouldTrack(Request $request): bool
    {
        if (! $request->hasSession() || ! $request->user()) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->expectsJson() || ! $request->acceptsHtml()) {
            return false;
        }

        if ($request->ajax() || $request->header('HX-Request') === 'true') {
            return false;
        }

        if ($request->filled('partial')) {
            return false;
        }

        $route = $request->route();
        $routeName = $route?->getName();

        return $route instanceof RoutingRoute && $this->isTrackableRouteName($routeName);
    }

    // Build navigation history.
    /**
     * @return array{url: string, route: string|null, recorded_at: int}|null
     */
    private function makeEntry(Request $request): ?array
    {
        $route = $request->route();
        $routeName = $route?->getName();

        // this check is slightly redundant -> already checked
        if (! $route instanceof RoutingRoute || ! $this->isTrackableRouteName($routeName)) {
            return null;
        }

        return [
            'url' => $this->normalizeUrl($request->fullUrl()),
            'route' => $routeName,
            'recorded_at' => (int) Carbon::now()->timestamp,
        ];
    }

    // acceptable crumbs -> dashboard, search, profile, and all index/show routes
    private function isTrackableRouteName(?string $routeName): bool
    {
        if (blank($routeName)) {
            return false;
        }

        if (in_array($routeName, ['dashboard', 'search', 'profile'], true)) {
            return true;
        }

        return Str::endsWith($routeName, ['.index', '.show']);
    }

    // Reset navigation history on arrival to dashboard, search, profile, and all index routes
    private function shouldResetOnArrival(?string $routeName): bool
    {
        if (blank($routeName)) {
            return false;
        }

        if (in_array($routeName, ['dashboard', 'search', 'profile'], true)) {
            return true;
        }

        return Str::endsWith($routeName, '.index');
    }

    /**
     * Remove invalid entries from the trail.
     *
     * @param  array<int, array<string, mixed>>  $trail
     * @return array<int, array<string, mixed>>
     */
    private function pruneTrail(Request $request, array $trail): array
    {
        $cutoff = Carbon::now()->subSeconds(self::MAX_AGE_SECONDS)->timestamp;
        $appHost = parse_url(config('app.url') ?: $request->getSchemeAndHttpHost(), PHP_URL_HOST);

        $trail = array_values(array_filter($trail, function (array $entry) use ($cutoff, $appHost) {
            $url = $entry['url'] ?? null;
            $recordedAt = (int) ($entry['recorded_at'] ?? 0);
            $host = parse_url((string) $url, PHP_URL_HOST);

            if (! is_string($url) || $url === '' || $recordedAt < $cutoff) {
                return false;
            }

            if ($host && $appHost && ! hash_equals($appHost, $host)) {
                return false;
            }

            return $this->isTrackableRouteName($entry['route'] ?? null);
        }));

        if (count($trail) > self::MAX_DEPTH) {
            $trail = array_slice($trail, -self::MAX_DEPTH);
        }

        return $trail;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isValidEntry(Request $request, array $entry): bool
    {
        $url = $entry['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return false;
        }

        try {
            $matchedRequest = Request::create($url, 'GET');
            $route = app('router')->getRoutes()->match($matchedRequest);
        } catch (\Throwable) {
            return false;
        }

        if (! $this->isTrackableRouteName($route->getName())) {
            return false;
        }

        $user = $request->user();

        if (! $user instanceof User || ! $user->isActive()) {
            return false;
        }

        return match ($route->getName()) {
            'agreements.show' => $this->canViewAgreement($user, $route->parameter('agreement')),
            'activities.show' => $this->canViewActivity($user, $route->parameter('activity')),
            default => true,
        };
    }

    private function canViewAgreement(User $user, mixed $agreement): bool
    {
        $agreement = $this->resolveAgreement($agreement);

        if (! $agreement instanceof Agreement) {
            return false;
        }

        return $user->can('view', $agreement);
    }

    private function canViewActivity(User $user, mixed $activity): bool
    {
        $activity = $this->resolveActivity($activity);

        if (! $activity instanceof Activity) {
            return false;
        }

        return $user->can('view', $activity);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function backLabelForEntry(array $entry): string
    {
        return match ($entry['route'] ?? null) {
            'dashboard' => 'Back to dashboard',
            'search' => 'Back to search',
            'profile' => 'Back to profile',
            'agreements.index' => 'Back to agreements',
            'agreements.show' => 'Back to agreement',
            'activities.index' => 'Back to activities',
            'activities.show' => 'Back to activity',
            'organizations.index' => 'Back to organizations',
            'organizations.show' => 'Back to organization',
            'projects.index' => 'Back to projects',
            'projects.show' => 'Back to project',
            'programs.index' => 'Back to programs',
            'programs.show' => 'Back to program',
            'states.index' => 'Back to states',
            'states.show' => 'Back to state',
            'teams.index' => 'Back to teams',
            'teams.show' => 'Back to team',
            'users.show' => 'Back to user',
            'admin.users.index' => 'Back to users',
            'logging-fields.index' => 'Back to logging fields',
            'contact-families.index' => 'Back to activity families',
            'activity-types.index' => 'Back to activity types',
            'certification-tools.index' => 'Back to certification tools',
            'certificates.index' => 'Back to certificates',
            default => 'Back',
        };
    }

    private function resolveAgreement(mixed $agreement): ?Agreement
    {
        if ($agreement instanceof Agreement) {
            return $agreement;
        }

        $agreementId = (int) $agreement;

        return $agreementId > 0
            ? Agreement::query()->find($agreementId)
            : null;
    }

    private function resolveActivity(mixed $activity): ?Activity
    {
        if ($activity instanceof Activity) {
            return $activity;
        }

        $activityId = (int) $activity;

        return $activityId > 0
            ? Activity::query()->with(['agreements', 'activityType.contactFamily'])->find($activityId)
            : null;
    }

    /**
     * get trail from session
     *
     * @return array<int, array<string, mixed>>
     */
    private function trail(Request $request): array
    {
        /** @var mixed $trail */
        $trail = $request->session()->get(self::SESSION_KEY);

        return is_array($trail) ? $trail : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $trail
     */
    private function storeTrail(Request $request, array $trail): void
    {
        $request->session()->put(self::SESSION_KEY, array_values($trail));
    }

    private function normalizeUrl(string $url): string
    {
        $fragmentlessUrl = strtok($url, '#');

        return is_string($fragmentlessUrl) ? $fragmentlessUrl : $url;
    }
}
