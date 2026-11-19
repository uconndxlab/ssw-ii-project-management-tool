<?php

namespace App\Enums;

enum CertificationInstrumentScoreUnit: string
{
    case Percent = 'percent';
    case Points = 'points';
    case Number = 'number';
    case PassFail = 'pass_fail';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage',
            self::Points => 'Points',
            self::Number => 'Number',
            self::PassFail => 'Pass/Fail',
        };
    }

    public function suffix(): ?string
    {
        return match ($this) {
            self::Percent => '%',
            self::Points => 'pts',
            self::Number, self::PassFail => null,
        };
    }

    /** Display text for a stored value: Pass/Fail fields store 1 = Pass and 0 = Fail. */
    public function format(string $value): string
    {
        return $this === self::PassFail
            ? ((float) $value === 1.0 ? 'Pass' : 'Fail')
            : rtrim(rtrim($value, '0'), '.').($this === self::Percent ? '%' : '');
    }
}
