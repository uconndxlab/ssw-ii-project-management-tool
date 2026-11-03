<?php

namespace App\Support;

/**
 * Class strings for the quiet chip system. Record kind is shown by label and icon, not by hue,
 * so every related-record chip is the same neutral chip; color is reserved for status.
 */
class EntityBadge
{
    /**
     * Neutral bordered chip for related records (linked chips hover to the accent; pickers use it as a token).
     */
    public static function relationClasses(string $kind, bool $pill = false): string
    {
        return 'app-chip';
    }

    /**
     * Quiet label for the record kind on show and edit pages.
     */
    public static function typeClasses(string $kind): string
    {
        return 'app-tag';
    }

    /**
     * Counts are plain text; this keeps callers that still ask for a class string working.
     */
    public static function countClasses(string $kind): string
    {
        return 'app-tag';
    }

    /**
     * Small metadata labels: roles, flags, taxonomy. Not clickable, so no border.
     */
    public static function categoryClasses(string $kind): string
    {
        return 'app-tag';
    }
}
