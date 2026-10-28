<?php

namespace App\Support;

use App\Models\User;
use App\Support\Authorization\UserAccess;

class AppNav
{
    /**
     * @return list<array{label: string, route: string, active: list<string>, icon: string, group: string}>
     */
    public static function primaryLinks(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        if ($user === null) {
            return [];
        }

        $access = UserAccess::for($user);

        $links = [
            self::link('Home', 'dashboard', ['dashboard'], 'bi-house', 'work'),
        ];

        if (! $access->isInput()) {
            $links[] = self::link('Agreements', 'agreements.index', ['agreements.*', 'certificate-enrollments.*'], 'bi-file-earmark-text', 'work');
        }

        $links[] = self::link('Activities', 'activities.index', ['activities.*'], 'bi-journal-check', 'work');

        if ($access->isSupervisor()) {
            $links[] = self::link('Supervisees', 'supervisees.index', ['supervisees.*'], 'bi-person-check', 'work');
        }

        if (! $access->isInput() && ! $access->hasAdmin()) {
            $links = array_merge($links, [
                self::link('Organizations', 'organizations.index', ['organizations.*'], 'bi-building', 'directory'),
                self::link('States', 'states.index', ['states.*'], 'bi-geo-alt', 'directory'),
                self::link('Projects', 'projects.index', ['projects.*'], 'bi-kanban', 'directory'),
                self::link('Programs', 'programs.index', ['programs.*'], 'bi-diagram-3', 'directory'),
                self::link('Teams', 'teams.index', ['teams.*'], 'bi-people', 'directory'),
            ]);

            if ($access->hasView()) {
                $links[] = self::link('Users', 'admin.users.index', ['admin.users.*', 'users.show'], 'bi-person-lines-fill', 'directory');
            }
        }

        return $links;
    }

    /**
     * @return list<array{header: string, items: list<array{label: string, route: string, active: list<string>, icon: string}>}>
     */
    public static function adminSections(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        if ($user === null || ! UserAccess::for($user)->hasAdmin()) {
            return [];
        }

        return [
            [
                'header' => 'Reference Data',
                'items' => [
                    self::item('Organizations', 'organizations.index', ['organizations.*'], 'bi-building'),
                    self::item('States', 'states.index', ['states.*'], 'bi-geo-alt'),
                ],
            ],
            [
                'header' => 'Agreement Setup',
                'items' => [
                    self::item('Projects', 'projects.index', ['projects.*'], 'bi-kanban'),
                    self::item('Programs', 'programs.index', ['programs.*'], 'bi-diagram-3'),
                ],
            ],
            [
                'header' => 'Activity Setup',
                'items' => [
                    self::item('Activity Families', 'contact-families.index', ['contact-families.*'], 'bi-collection'),
                    self::item('Activity Types', 'activity-types.index', ['activity-types.*'], 'bi-tags'),
                    self::item('Logging Fields', 'logging-fields.index', ['logging-fields.*'], 'bi-input-cursor-text'),
                ],
            ],
            [
                'header' => 'Certification Setup',
                'items' => [
                    self::item('Certification Tools', 'certification-tools.index', ['certification-tools.*'], 'bi-tools'),
                    self::item('Certificates', 'certificates.index', ['certificates.*'], 'bi-award'),
                ],
            ],
            [
                'header' => 'People',
                'items' => [
                    self::item('Teams', 'teams.index', ['teams.*'], 'bi-people'),
                    self::item('Users', 'admin.users.index', ['admin.users.*', 'users.show'], 'bi-person-lines-fill'),
                ],
            ],
        ];
    }

    /**
     * Sidebar groups: Work and Directory from the primary links, then each admin setup section.
     * Both sources already apply the role rules, so the sidebar adds no permission logic of its own.
     *
     * @return list<array{header: string, items: list<array{label: string, route: string, active: list<string>, icon: string}>}>
     */
    public static function railGroups(?User $user = null): array
    {
        $links = self::primaryLinks($user);
        $groups = [];

        foreach (['work' => 'Work', 'directory' => 'Directory'] as $key => $header) {
            $items = array_values(array_filter($links, fn (array $link) => $link['group'] === $key));

            if ($items !== []) {
                $groups[] = ['header' => $header, 'items' => $items];
            }
        }

        return array_merge($groups, self::adminSections($user));
    }

    /**
     * @param  list<string>  $patterns
     */
    public static function isActive(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function showSearch(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user !== null && ! UserAccess::for($user)->isInput();
    }

    /**
     * @param  list<string>  $active
     * @return array{label: string, route: string, active: list<string>, icon: string, group: string}
     */
    private static function link(string $label, string $route, array $active, string $icon, string $group): array
    {
        return [...self::item($label, $route, $active, $icon), 'group' => $group];
    }

    /**
     * @param  list<string>  $active
     * @return array{label: string, route: string, active: list<string>, icon: string}
     */
    private static function item(string $label, string $route, array $active, string $icon): array
    {
        return ['label' => $label, 'route' => $route, 'active' => $active, 'icon' => $icon];
    }
}
