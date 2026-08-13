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
        if (!$request->hasSession()) {
            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    public function track(Request $request): void
    {
        if (!$this->shouldTrack($request)) {
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

    public function resolve(Request $request): ?array
    {
        if (!$request->hasSession() || !$request->user()) {
            return null;
        }

        $trail = $this->pruneTrail($request, $this->trail($request));
        $currentUrl = $this->normalizeUrl($request->fullUrl());
        $changed = false;

        for ($index = count($trail) - 1; $index >= 0; $index--) {
            $entry = $trail[$index];
            $entryUrl = $entry['url'] ?? null;

            if (!$entryUrl || $entryUrl === $currentUrl) {
                continue;
            }

            if (!$this->isValidEntry($request, $entry)) {
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

    /**
     * @return array<int, array{label: string, url: ?string, current: bool}>
     */
    public function breadcrumbs(Request $request, ?string $currentLabel = null): array
    {
        if (!$request->hasSession() || !$request->user()) {
            return $currentLabel ? [[
                'label' => $currentLabel,
                'url' => null,
                'current' => true,
            ]] : [];
        }

        $trail = $this->pruneTrail($request, $this->trail($request));
        $currentUrl = $this->normalizeUrl($request->fullUrl());
        $items = [];
        $changed = false;

        foreach ($trail as $index => $entry) {
            $entryUrl = $entry['url'] ?? null;

            if (!$entryUrl || $entryUrl === $currentUrl) {
                continue;
            }

            if (!$this->isValidEntry($request, $entry)) {
                unset($trail[$index]);
                $changed = true;

                continue;
            }

            $items[] = [
                'label' => $this->crumbLabelForEntry($entry),
                'url' => $entryUrl,
                'current' => false,
            ];
        }

        if ($currentLabel) {
            $items[] = [
                'label' => $currentLabel,
                'url' => null,
                'current' => true,
            ];
        } elseif ($items !== []) {
            $lastIndex = array_key_last($items);
            $items[$lastIndex]['url'] = null;
            $items[$lastIndex]['current'] = true;
        }

        if ($changed) {
            $this->storeTrail($request, array_values($trail));
        }

        return $items;
    }

    private function shouldTrack(Request $request): bool
    {
        if (!$request->hasSession() || !$request->user()) {
            return false;
        }

        if (!$request->isMethod('GET')) {
            return false;
        }

        if ($request->expectsJson() || !$request->acceptsHtml()) {
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

    // build crumb info
    private function makeEntry(Request $request): ?array
    {
        $route = $request->route();
        $routeName = $route?->getName();

        // this check is slightly redundant -> already checked
        if (!$route instanceof RoutingRoute || !$this->isTrackableRouteName($routeName)) {
            return null;
        }

        return [
            'url' => $this->normalizeUrl($request->fullUrl()),
            'route' => $routeName,
            'crumb_label' => $this->routeCrumbLabel($route),
            'recorded_at' => Carbon::now()->timestamp,
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

    // reset breadcrumbs on arrival to dashboard, search, profile, and all index routes
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
     * remove invalid crumbs form trail
     *
     * @param  array<int, array<string, mixed>>  $trail
     * @return array<int, array<string, mixed>>
     */
    private function pruneTrail(Request $request, array $trail): array
    {
        $cutoff = Carbon::now()->subSeconds(self::MAX_AGE_SECONDS)->timestamp;
        $appHost = parse_url(config('app.url') ?: $request->getSchemeAndHttpHost(), PHP_URL_HOST);

        $trail = array_values(array_filter($trail, function ($entry) use ($cutoff, $appHost) {
            if (!is_array($entry)) {
                return false;
            }

            $url = $entry['url'] ?? null;
            $recordedAt = (int) ($entry['recorded_at'] ?? 0);
            $host = parse_url((string) $url, PHP_URL_HOST);

            if (!is_string($url) || $url === '' || $recordedAt < $cutoff) {
                return false;
            }

            if ($host && $appHost && !hash_equals($appHost, $host)) {
                return false;
            }

            return $this->isTrackableRouteName($entry['route'] ?? null);
        }));

        if (count($trail) > self::MAX_DEPTH) {
            $trail = array_slice($trail, -self::MAX_DEPTH);
        }

        return $trail;
    }

    private function isValidEntry(Request $request, array $entry): bool
    {
        $url = $entry['url'] ?? null;

        if (!is_string($url) || $url === '') {
            return false;
        }

        try {
            $matchedRequest = Request::create($url, 'GET');
            $route = app('router')->getRoutes()->match($matchedRequest);
        } catch (\Throwable) {
            return false;
        }

        if (!$route instanceof RoutingRoute || !$this->isTrackableRouteName($route->getName())) {
            return false;
        }

        $user = $request->user();

        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        if (!$this->passesRoleMiddleware($user, $route)) {
            return false;
        }

        return match ($route->getName()) {
            'agreements.show' => $this->canViewAgreement($user, $route->parameter('agreement')),
            'activities.show' => $this->canViewActivity($user, $route->parameter('activity')),
            default => true,
        };
    }

    private function passesRoleMiddleware(User $user, RoutingRoute $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (!str_starts_with($middleware, 'role:')) {
                continue;
            }

            $roles = array_filter(explode(',', substr($middleware, 5)));

            if ($user->isAdmin()) {
                return true;
            }

            return in_array($user->role, $roles, true);
        }

        return true;
    }

    private function canViewAgreement(User $user, mixed $agreement): bool
    {
        $agreement = $this->resolveAgreement($agreement);

        if (!$agreement instanceof Agreement) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $agreement->active && $user->hasAccessToAgreement($agreement);
    }

    private function canViewActivity(User $user, mixed $activity): bool
    {
        $activity = $this->resolveActivity($activity);

        if (!$activity instanceof Activity) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $activity->loadMissing('agreements');
        $hasAgreementAccess = $activity->agreements->contains(
            fn (Agreement $agreement) => $user->hasAccessToAgreement($agreement),
        );

        return $hasAgreementAccess && (int) $activity->user_id === (int) $user->id;
    }

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
            default => 'Back',
        };
    }

    private function crumbLabelForEntry(array $entry): string
    {
        $crumbLabel = $entry['crumb_label'] ?? null;

        if (is_string($crumbLabel) && $crumbLabel !== '') {
            return $crumbLabel;
        }

        return $this->fallbackCrumbLabel($entry['route'] ?? null);
    }

    private function routeCrumbLabel(RoutingRoute $route): string
    {
        return match ($route->getName()) {
            'dashboard' => 'Dashboard',
            'search' => 'Search',
            'profile' => 'Profile',
            'agreements.show' => $this->resolveAgreement($route->parameter('agreement'))?->name ?? 'Agreement',
            'activities.show' => $this->activityCrumbLabel($this->resolveActivity($route->parameter('activity'))),
            'organizations.show' => $route->parameter('organization')?->name ?? 'Organization',
            'projects.show' => $route->parameter('project')?->name ?? 'Project',
            'programs.show' => $route->parameter('program')?->name ?? 'Program',
            'states.show' => $route->parameter('state')?->name ?? 'State',
            'teams.show' => $route->parameter('team')?->name ?? 'Team',
            'users.show' => $route->parameter('user')?->name ?? 'User',
            default => $this->fallbackCrumbLabel($route->getName()),
        };
    }

    private function fallbackCrumbLabel(?string $routeName): string
    {
        return match ($routeName) {
            'dashboard' => 'Dashboard',
            'search' => 'Search',
            'profile' => 'Profile',
            'agreements.index' => 'Agreements',
            'agreements.show' => 'Agreement',
            'activities.index' => 'Activities',
            'activities.show' => 'Activity',
            'organizations.index' => 'Organizations',
            'organizations.show' => 'Organization',
            'projects.index' => 'Projects',
            'projects.show' => 'Project',
            'programs.index' => 'Programs',
            'programs.show' => 'Program',
            'states.index' => 'States',
            'states.show' => 'State',
            'teams.index' => 'Teams',
            'teams.show' => 'Team',
            'users.show' => 'User',
            'admin.users.index' => 'Users',
            'logging-fields.index' => 'Logging Fields',
            'contact-families.index' => 'Activity Families',
            'activity-types.index' => 'Activity Types',
            default => 'Current Page',
        };
    }

    private function activityCrumbLabel(?Activity $activity): string
    {
        if (!$activity instanceof Activity) {
            return 'Activity';
        }

        $typeName = $activity->relationLoaded('activityType')
            ? $activity->activityType?->name
            : $activity->activityType()->value('name');
        $dateLabel = $activity->engagement_date?->format('M j, Y');

        return collect([$typeName, $dateLabel])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->implode(' · ') ?: 'Activity';
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
            ? Activity::query()->with('agreements')->find($activityId)
            : null;
    }

    /**
     * get trail from session
     *
     * @return array<int, array<string, mixed>>
     */
    private function trail(Request $request): array
    {
        $trail = $request->session()->get(self::SESSION_KEY, []);

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
