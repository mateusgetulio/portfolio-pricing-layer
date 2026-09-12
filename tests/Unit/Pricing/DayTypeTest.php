<?php

use App\Pricing\Enums\DayType;

it('treats Friday and Saturday nights as weekend nights', function (string $date, DayType $dayType) {
    expect(DayType::forNight(new DateTimeImmutable($date)))->toBe($dayType);
})->with([
    'Thursday' => ['2026-09-17', DayType::Weekday],
    'Friday' => ['2026-09-18', DayType::Weekend],
    'Saturday' => ['2026-09-19', DayType::Weekend],
    'Sunday' => ['2026-09-20', DayType::Weekday],
]);
