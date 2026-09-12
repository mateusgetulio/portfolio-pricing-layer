<?php

use App\Pricing\Data\Unit;
use App\Pricing\DemoScenario;
use App\Pricing\Enums\DayType;
use App\Pricing\Enums\NightStatus;
use App\Pricing\Exceptions\InvalidDemoScenario;

beforeEach(function () {
    $this->demo = new DemoScenario(portfolioFixture(), weekendNight: '2026-09-19', weekdayNight: '2026-09-20');
});

it('books the first units by ID and prices the weekend night', function () {
    $snapshot = $this->demo->build(8, 8, DayType::Weekend);

    $booked = array_values(array_filter($snapshot->units, fn (Unit $unit) => $unit->nights[0]->status === NightStatus::Booked));

    expect(array_map(fn (Unit $unit) => $unit->id, $booked))->toBe(['unit-01', 'unit-02', 'unit-03', 'unit-04', 'unit-05', 'unit-06', 'unit-07', 'unit-08'])
        ->and($snapshot->leadTimeDays(new DateTimeImmutable('2026-09-19')))->toBe(8)
        ->and($snapshot->units[11]->nights[0]->basePriceCents)->toBe(13900);
});

it('uses the weekday night prices without the fixture statuses', function () {
    $snapshot = $this->demo->build(8, 8, DayType::Weekday);

    expect($snapshot->units[11]->nights[0]->date->format('Y-m-d'))->toBe('2026-09-20')
        ->and($snapshot->units[11]->nights[0]->status)->toBe(NightStatus::Available)
        ->and($snapshot->units[11]->nights[0]->basePriceCents)->toBe(11700);
});

it('reproduces the worked example through the pricer', function () {
    $saturday = new DateTimeImmutable('2026-09-19');

    $recommendations = pricer()->price($this->demo->build(8, 8, DayType::Weekend));

    expect($recommendations->forUnitOn('unit-12', $saturday)->recommendedPriceCents)->toBe(12800)
        ->and($recommendations->forUnitOn('unit-11', $saturday)->recommendedPriceCents)->toBe(13000);
});

it('rejects scenarios outside the slider ranges', function (int $booked, int $leadTimeDays, string $message) {
    expect(fn () => $this->demo->build($booked, $leadTimeDays, DayType::Weekend))->toThrow(InvalidDemoScenario::class, $message);
})->with([
    'negative booked units' => [-1, 8, 'Booked units must be between 0 and 12, got -1.'],
    'more booked units than the group has' => [13, 8, 'Booked units must be between 0 and 12, got 13.'],
    'negative lead time' => [8, -1, 'Lead time must be between 0 and 60 days, got -1.'],
    'lead time beyond the slider' => [8, 61, 'Lead time must be between 0 and 60 days, got 61.'],
]);
