<?php

namespace App\Support;

use App\Models\User;
use App\Support\Authorization\UserAccess;

class AppNav
{
    /**
     * @return list<array{label: string, route: string, active: list<string>}>
     */
    public static function primaryLinks(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        if ($user === null) {
            return [];
        }

        $access = UserAccess::for($user);

        if ($access->isInput()) {
            return [
                [
                    'label' => 'Home',
                    'route' => 'dashboard',
                    'active' => ['dashboard'],
                ],
                [
                    'label' => 'Activities',
                    'route' => 'activities.index',
                    'active' => ['activities.*'],
                ],
            ];
        }

        $links = [
            [
                'label' => 'Home',
                'route' => 'dashboard',
                'active' => ['dashboard'],
            ],
            [
                'label' => 'Agreements',
                'route' => 'agreements.index',
                'active' => ['agreements.*'],
            ],
            [
                'label' => 'Activities',
                'route' => 'activities.index',
                'active' => ['activities.*'],
            ],
        ];

        if (! $access->hasAdmin()) {
            $links = array_merge($links, [
                [
                    'label' => 'Organizations',
                    'route' => 'organizations.index',
                    'active' => ['organizations.*'],
                ],
                [
                    'label' => 'States',
                    'route' => 'states.index',
                    'active' => ['states.*'],
                ],
                [
                    'label' => 'Projects',
                    'route' => 'projects.index',
                    'active' => ['projects.*'],
                ],
                [
                    'label' => 'Programs',
                    'route' => 'programs.index',
                    'active' => ['programs.*'],
                ],
                [
                    'label' => 'Teams',
                    'route' => 'teams.index',
                    'active' => ['teams.*'],
                ],
            ]);
        }

        if ($access->hasView() && ! $access->hasAdmin()) {
            $links[] = [
                'label' => 'Users',
                'route' => 'admin.users.index',
                'active' => ['admin.users.*', 'users.show'],
            ];
        }

        if ($access->isSupervisor()) {
            $links[] = [
                'label' => 'Supervisees',
                'route' => 'supervisees.index',
                'active' => ['supervisees.*'],
            ];
        }

        return $links;
    }

    /**
     * @return list<array{header: string, items: list<array{label: string, route: string, active: list<string>}>}>
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
                    ['label' => 'Organizations', 'route' => 'organizations.index', 'active' => ['organizations.*']],
                    ['label' => 'States', 'route' => 'states.index', 'active' => ['states.*']],
                ],
            ],
            [
                'header' => 'Agreement Setup',
                'items' => [
                    ['label' => 'Projects', 'route' => 'projects.index', 'active' => ['projects.*']],
                    ['label' => 'Programs', 'route' => 'programs.index', 'active' => ['programs.*']],
                ],
            ],
            [
                'header' => 'Activity Setup',
                'items' => [
                    ['label' => 'Activity Families', 'route' => 'contact-families.index', 'active' => ['contact-families.*']],
                    ['label' => 'Activity Types', 'route' => 'activity-types.index', 'active' => ['activity-types.*']],
                    ['label' => 'Logging Fields', 'route' => 'logging-fields.index', 'active' => ['logging-fields.*']],
                ],
            ],
            [
                'header' => 'People',
                'items' => [
                    ['label' => 'Teams', 'route' => 'teams.index', 'active' => ['teams.*']],
                    ['label' => 'Users', 'route' => 'admin.users.index', 'active' => ['admin.users.*', 'users.show']],
                ],
            ],
        ];
    }

    public static function isActive(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function adminIsActive(): bool
    {
        foreach (self::adminSections() as $section) {
            foreach ($section['items'] as $item) {
                if (self::isActive($item['active'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function showSearch(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user !== null && ! UserAccess::for($user)->isInput();
    }
}
