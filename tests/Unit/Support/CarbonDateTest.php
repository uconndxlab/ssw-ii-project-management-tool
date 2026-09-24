<?php

use App\Support\CarbonDate;
use Carbon\Carbon;

test('carbon date parses carbon datetime strings and empty values', function () {
    $carbon = Carbon::parse('2026-03-15');

    expect(CarbonDate::parse($carbon)?->toDateString())->toBe('2026-03-15')
        ->and(CarbonDate::parse(new DateTimeImmutable('2026-04-01'))?->toDateString())->toBe('2026-04-01')
        ->and(CarbonDate::parse('2026-05-02')?->toDateString())->toBe('2026-05-02')
        ->and(CarbonDate::parse(''))->toBeNull()
        ->and(CarbonDate::parse(null))->toBeNull();
});
