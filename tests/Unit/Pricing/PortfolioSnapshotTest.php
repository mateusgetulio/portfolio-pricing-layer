<?php

use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\Unit;
use App\Pricing\Enums\NightStatus;
use App\Pricing\Exceptions\InvalidPortfolioSnapshot;

it('builds a snapshot from valid input', function () {
    $snapshot = PortfolioSnapshot::fromArray(snapshotData());

    $unit = $snapshot->units[0];

    expect($snapshot->asOf->format('Y-m-d'))->toBe('2026-09-11')
        ->and($snapshot->groups)->toHaveCount(1)
        ->and($unit->groupId)->toBe('downtown-1br')
        ->and($unit->floorPriceCents)->toBe(9500)
        ->and($unit->nights[0]->status)->toBe(NightStatus::Available)
        ->and($unit->nights[0]->basePriceCents)->toBe(14200);
});

it('lists the units of one group', function () {
    $data = snapshotData();
    data_set($data, 'groups.1', ['id' => 'harbor-2br', 'name' => 'Harbor 2BR']);
    data_set($data, 'units.1', sampleUnit(['id' => 'unit-02', 'group_id' => 'harbor-2br']));

    $snapshot = PortfolioSnapshot::fromArray($data);

    expect(array_map(fn (Unit $unit) => $unit->id, $snapshot->unitsInGroup('harbor-2br')))->toBe(['unit-02']);
});

it('finds a unit night by date', function () {
    $unit = PortfolioSnapshot::fromArray(snapshotData())->units[0];

    expect($unit->nightOn(new DateTimeImmutable('2026-09-19'))?->basePriceCents)->toBe(14200)
        ->and($unit->nightOn(new DateTimeImmutable('2026-09-20')))->toBeNull();
});

it('rejects invalid input with a clear message', function (string $path, mixed $value, string $message) {
    $data = snapshotData();
    data_set($data, $path, $value);

    expect(fn () => PortfolioSnapshot::fromArray($data))->toThrow(InvalidPortfolioSnapshot::class, $message);
})->with([
    'floor price above ceiling price' => ['units.0.floor_price_cents', 30000, 'Unit [unit-01] has a floor price above its ceiling price.'],
    'zero floor price' => ['units.0.floor_price_cents', 0, 'Unit [unit-01] needs positive floor and ceiling prices.'],
    'negative base price' => ['units.0.nights.0.base_price_cents', -100, 'The unit [unit-01] night on 2026-09-19 needs a positive base_price_cents, got -100.'],
    'trailing occupancy above one' => ['units.0.trailing_occupancy', 1.2, 'Unit [unit-01] trailing occupancy must be between 0 and 1, got 1.2.'],
    'negative trailing occupancy' => ['units.0.trailing_occupancy', -0.1, 'Unit [unit-01] trailing occupancy must be between 0 and 1, got -0.1.'],
    'negative history nights' => ['units.0.history_nights', -5, 'Unit [unit-01] cannot have negative nights of history.'],
    'unknown group' => ['units.0.group_id', 'uptown', 'Unit [unit-01] references unknown group [uptown].'],
    'duplicate unit ID' => ['units.1', sampleUnit(), 'Unit ID [unit-01] is used more than once.'],
    'duplicate group ID' => ['groups.1', ['id' => 'downtown-1br', 'name' => 'Copy'], 'Group ID [downtown-1br] is used more than once.'],
    'unknown night status' => ['units.0.nights.0.status', 'reserved', 'The unit [unit-01] night status must be booked, available or blocked, got [reserved].'],
    'malformed date' => ['units.0.nights.0.date', '2026-02-30', 'The unit [unit-01] night date must be a Y-m-d date, got [2026-02-30].'],
    'night before the snapshot date' => ['units.0.nights.0.date', '2026-09-10', 'Unit [unit-01] has a night on 2026-09-10, before the snapshot date 2026-09-11.'],
    'duplicate night' => ['units.0.nights.1', sampleNight(), 'Unit [unit-01] has more than one entry for the same night.'],
    'price given as a string' => ['units.0.floor_price_cents', '9500', 'The unit [unit-01] floor_price_cents must be an integer.'],
    'missing group name' => ['groups.0.name', null, 'The group name must be a string.'],
]);
