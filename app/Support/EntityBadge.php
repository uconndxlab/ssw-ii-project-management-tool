<?php

namespace App\Support;

class EntityBadge
{
    /**
     * Subtle bordered chips for linked related records (tables, summaries, token pickers).
     */
    public static function relationClasses(string $kind, bool $pill = false): string
    {
        $classes = match ($kind) {
            'project' => 'bg-primary-subtle text-primary-emphasis border',
            'program' => 'bg-warning-subtle text-warning-emphasis border',
            'agreement' => 'bg-success-subtle text-success-emphasis border',
            'state' => 'bg-info-subtle text-info-emphasis border',
            'team' => 'bg-secondary-subtle text-secondary-emphasis border',
            'organization' => 'entity-badge--organization border',
            'activity' => 'bg-secondary-subtle text-secondary-emphasis border',
            'user' => 'bg-light text-dark border',
            default => 'bg-secondary-subtle text-secondary-emphasis border',
        };

        if ($pill) {
            $classes .= ' rounded-pill';
        }

        return $classes;
    }

    // labels on show/edit pages for entities
    public static function typeClasses(string $kind): string
    {
        return match ($kind) {
            'project' => 'bg-primary-subtle text-primary-emphasis border',
            'program' => 'bg-warning-subtle text-warning-emphasis border',
            'agreement' => 'bg-success-subtle text-success-emphasis border',
            'state' => 'bg-info-subtle text-info-emphasis border',
            'team' => 'bg-secondary-subtle text-secondary-emphasis border',
            'organization' => 'entity-badge--organization border',
            'activity' => 'bg-secondary-subtle text-secondary-emphasis border',
            'activity-type' => 'bg-secondary-subtle text-secondary-emphasis border',
            'contact-family' => 'bg-secondary-subtle text-secondary-emphasis border',
            'logging-field' => 'bg-secondary-subtle text-secondary-emphasis border',
            'user' => 'bg-light text-dark border',
            default => 'bg-secondary-subtle text-secondary-emphasis border',
        };
    }

    /**
     * Entity-colored count pills (same hue as the entity, neutral pill shape).
     */
    public static function countClasses(string $kind): string
    {
        return self::relationClasses($kind, pill: true);
    }

    /**
     * Small metadata labels — roles, flags, taxonomy (lower in the entity hierarchy).
     */
    public static function categoryClasses(string $kind): string
    {
        return match ($kind) {
            'pi' => 'bg-warning-subtle text-warning-emphasis border',
            'kfs' => 'bg-primary-subtle text-primary-emphasis border',
            'payor-source', 'recipient', 'role' => 'bg-light text-muted border',
            'contact-family' => 'bg-light text-muted border',
            'activity-type' => 'bg-light text-muted border',
            default => 'bg-light text-muted border',
        };
    }
}
