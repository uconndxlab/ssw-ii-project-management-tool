<?php

namespace App\Enums;

enum AgreementTimeTrackingRequirement: string
{
    case ByContact = 'by_contact';
    case ByUser = 'by_user';

    public static function options(): array
    {
        return array_merge([
            [
                'value' => 'none',
                'label' => 'None',
                'description' => 'No time tracking requirement.',
            ],
        ], array_map(function (self $case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ];
        }, self::cases()));
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::ByContact => 'By Contact',
            self::ByUser => 'By User',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ByContact => 'Time must be tracked at the contact level.',
            self::ByUser => 'Time must be tracked for both contact and user activity tracking.',
        };
    }
}