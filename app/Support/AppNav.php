<?php

namespace App\Support;

class AppNav
{
    /**
     * Primary workflow links shown to all authenticated users.
     *
     * @return list<array{label: string, route: string, active: list<string>}>
     */
    public static function primaryLinks(): array
    {
        return [
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
            [
                'label' => 'Organizations',
                'route' => 'organizations.index',
                'active' => ['organizations.*'],
            ],
        ];
    }

    /**
     * Admin configuration menu grouped by section.
     *
     * @return list<array{header: string, items: list<array{label: string, route: string, active: list<string>}>}>
     */
    public static function adminSections(): array
    {
        return [
            [
                'header' => 'Reference Data',
                'items' => [
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
                    ['label' => 'Contact Families', 'route' => 'contact-families.index', 'active' => ['contact-families.*']],
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
}
