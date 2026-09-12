<?php

use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\Recommendation;
use App\Pricing\Enums\PricingMode;
use App\Pricing\Enums\PricingRule;

it('prices the worked example with two price leaders', function () {
    $saturday = new DateTimeImmutable('2026-09-19');

    $recommendations = pricer()->price(portfolioFixture());

    $assessment = $recommendations->assessmentFor('downtown-1br', $saturday);

    expect($assessment->mode)->toBe(PricingMode::Fill)
        ->and($assessment->targetBooked)->toBe(10)
        ->and($assessment->leaderBudget)->toBe(2)
        ->and($assessment->discountRate)->toBe(0.08)
        ->and($recommendations->forUnitOn('unit-09', $saturday)->recommendedPriceCents)->toBe(14200)
        ->and($recommendations->forUnitOn('unit-10', $saturday)->recommendedPriceCents)->toBe(14000)
        ->and($recommendations->forUnitOn('unit-11', $saturday)->recommendedPriceCents)->toBe(13000)
        ->and($recommendations->forUnitOn('unit-12', $saturday)->recommendedPriceCents)->toBe(12800);
});

it('explains every decision of the worked example', function (string $unitId, string $reason) {
    $recommendation = pricer()->price(portfolioFixture())->forUnitOn($unitId, new DateTimeImmutable('2026-09-19'));

    expect($recommendation->reason)->toBe($reason);
})->with([
    'weakest unit' => ['unit-12', '-8%: 8 of 12 booked, target 10 by 8 days out, leader budget 2, weakest recent occupancy in the group.'],
    'second weakest unit' => ['unit-11', '-6%: 8 of 12 booked, target 10 by 8 days out, leader budget 2, 2nd weakest recent occupancy in the group.'],
    'stronger unit held' => ['unit-09', 'Held: 8 of 12 booked, target 10 by 8 days out, the leader budget of 2 went to weaker units.'],
    'booked unit' => ['unit-01', 'Booked night, never repriced.'],
]);

it('walks through fill, hold and protect as bookings rise', function (int $booked, PricingMode $mode, array $leaderDiscounts) {
    $recommendations = pricer()->price(twelveApartments($booked));

    $leaders = array_values(array_filter($recommendations->forGroupOn('downtown-1br', new DateTimeImmutable('2026-09-19')), fn (Recommendation $recommendation) => $recommendation->isPriceLeader()));
    usort($leaders, fn (Recommendation $first, Recommendation $second) => $first->leaderRank <=> $second->leaderRank);

    expect($recommendations->assessmentFor('downtown-1br', new DateTimeImmutable('2026-09-19'))->mode)->toBe($mode)
        ->and(array_map(fn (Recommendation $leader) => $leader->assignedDiscount, $leaders))->toBe($leaderDiscounts);
})->with([
    '3 of 12 booked' => [3, PricingMode::Fill, [0.15, 0.11, 0.08, 0.06, 0.05, 0.04, 0.03]],
    '8 of 12 booked' => [8, PricingMode::Fill, [0.08, 0.06]],
    '10 of 12 booked' => [10, PricingMode::Hold, []],
    '11 of 12 booked' => [11, PricingMode::Protect, []],
    'fully booked' => [12, PricingMode::Protect, []],
]);

it('explains hold and protect decisions', function (int $booked, string $unitId, string $reason) {
    $recommendation = pricer()->price(twelveApartments($booked))->forUnitOn($unitId, new DateTimeImmutable('2026-09-19'));

    expect($recommendation->reason)->toBe($reason);
})->with([
    'on pace' => [10, 'unit-12', 'Held: 10 of 12 booked, target 10 by 8 days out, on pace.'],
    'ahead of pace' => [11, 'unit-12', 'Protected: 11 of 12 booked, target 10 by 8 days out, ahead of pace.'],
]);

it('fills when behind a high target even though most units are booked', function () {
    $units = array_map(fn (int $index) => ['id' => "unit-{$index}", 'status' => $index <= 6 ? 'booked' : 'available'], range(1, 7));

    $recommendations = pricer()->price(scenario($units, night: '2026-09-19', leadTimeDays: 2));

    expect($recommendations->assessmentFor('downtown-1br', new DateTimeImmutable('2026-09-19'))->mode)->toBe(PricingMode::Fill);
});

it('protects when ahead of a low target far out', function () {
    $units = array_map(fn (int $index) => ['id' => "unit-{$index}", 'status' => $index <= 4 ? 'booked' : 'available'], range(1, 10));

    $recommendations = pricer()->price(scenario($units, night: '2026-11-10', leadTimeDays: 60));

    expect($recommendations->assessmentFor('downtown-1br', new DateTimeImmutable('2026-11-10'))->mode)->toBe(PricingMode::Protect);
});

it('never reprices booked or blocked nights and leaves blocked units out of the sellable count', function () {
    $units = array_map(fn (int $index) => [
        'id' => sprintf('unit-%02d', $index),
        'status' => match (true) {
            $index <= 8 => 'booked',
            $index === 12 => 'blocked',
            default => 'available',
        },
        'base' => $index === 12 ? 15000 : 14000,
    ], range(1, 12));

    $recommendations = pricer()->price(scenario($units));
    $saturday = new DateTimeImmutable('2026-09-19');
    $blocked = $recommendations->forUnitOn('unit-12', $saturday);

    expect($recommendations->assessmentFor('downtown-1br', $saturday)->sellableUnits())->toBe(11)
        ->and($blocked->rule)->toBe(PricingRule::BlockedNight)
        ->and($blocked->recommendedPriceCents)->toBe(15000)
        ->and($recommendations->forUnitOn('unit-01', $saturday)->recommendedPriceCents)->toBe(14000);
});

it('discounts relative to each unit base price instead of forcing a price order', function () {
    $recommendations = pricer()->price(scenario([
        ['id' => 'booked', 'status' => 'booked'],
        ['id' => 'strong', 'status' => 'available', 'occupancy' => 0.9, 'base' => 13000],
        ['id' => 'weak', 'status' => 'available', 'occupancy' => 0.4, 'base' => 16000],
    ]));
    $saturday = new DateTimeImmutable('2026-09-19');

    $strong = $recommendations->forUnitOn('strong', $saturday);
    $weak = $recommendations->forUnitOn('weak', $saturday);

    expect($weak->discount())->toBeGreaterThan($strong->discount())
        ->and($weak->recommendedPriceCents)->toBeGreaterThan($strong->recommendedPriceCents);
});

it('limits a discount at the floor price and says so', function () {
    $recommendations = pricer()->price(scenario([
        ['id' => 'booked', 'status' => 'booked'],
        ['id' => 'strong', 'status' => 'available', 'occupancy' => 0.9],
        ['id' => 'weak', 'status' => 'available', 'occupancy' => 0.4, 'base' => 10000, 'floor' => 9800],
    ]));

    $weak = $recommendations->forUnitOn('weak', new DateTimeImmutable('2026-09-19'));

    expect($weak->recommendedPriceCents)->toBe(9800)
        ->and($weak->reason)->toBe('-2%: 1 of 3 booked, target 3 by 8 days out, leader budget 2, weakest recent occupancy in the group. Limited to the floor price.');
});

it('passes groups that are too small through unchanged', function () {
    $recommendations = pricer()->price(scenario([
        ['id' => 'first', 'status' => 'available', 'base' => 14000],
        ['id' => 'second', 'status' => 'available', 'base' => 15000],
    ]));
    $saturday = new DateTimeImmutable('2026-09-19');

    $first = $recommendations->forUnitOn('first', $saturday);

    expect($recommendations->assessmentFor('downtown-1br', $saturday)->mode)->toBe(PricingMode::PassThrough)
        ->and($first->recommendedPriceCents)->toBe(14000)
        ->and($first->reason)->toBe('Unchanged: a group of 2 units is too small for portfolio pricing.');
});

it('does not make a unit without history the first price leader', function () {
    $recommendations = pricer()->price(scenario([
        ['id' => 'booked-strong', 'status' => 'booked', 'occupancy' => 0.9],
        ['id' => 'booked-average', 'status' => 'booked', 'occupancy' => 0.6],
        ['id' => 'new-listing', 'status' => 'available', 'occupancy' => 0.0, 'history' => 10],
        ['id' => 'weak', 'status' => 'available', 'occupancy' => 0.3],
    ], night: '2026-09-24', leadTimeDays: 14));
    $thursday = new DateTimeImmutable('2026-09-24');

    expect($recommendations->forUnitOn('weak', $thursday)->rule)->toBe(PricingRule::PriceLeader)
        ->and($recommendations->forUnitOn('new-listing', $thursday)->rule)->toBe(PricingRule::HeldForWeakerUnits);
});

it('breaks ties in booking strength by unit ID in string order', function () {
    $recommendations = pricer()->price(scenario([
        ['id' => 'a', 'status' => 'booked', 'occupancy' => 0.9],
        ['id' => 'b', 'status' => 'booked', 'occupancy' => 0.9],
        ['id' => '9', 'status' => 'available', 'occupancy' => 0.5],
        ['id' => '10', 'status' => 'available', 'occupancy' => 0.5],
    ], night: '2026-09-24', leadTimeDays: 14));
    $thursday = new DateTimeImmutable('2026-09-24');

    expect($recommendations->forUnitOn('10', $thursday)->rule)->toBe(PricingRule::PriceLeader)
        ->and($recommendations->forUnitOn('9', $thursday)->rule)->toBe(PricingRule::HeldForWeakerUnits);
});

it('never gives more discount than assigned when rounding to a whole currency unit', function () {
    $leaders = array_filter(
        pricer()->price(twelveApartments(3))->forGroupOn('downtown-1br', new DateTimeImmutable('2026-09-19')),
        fn (Recommendation $recommendation) => $recommendation->isPriceLeader(),
    );

    expect($leaders)->toHaveCount(7)
        ->each(fn ($leader) => $leader->discount()->toBeLessThanOrEqual($leader->value->assignedDiscount + 0.000001));
});

it('bases the headline percentage on the final price after a ceiling clamp', function () {
    $units = array_map(fn (int $index) => [
        'id' => sprintf('unit-%02d', $index),
        'status' => $index <= 8 ? 'booked' : 'available',
        'occupancy' => [9 => 0.85, 10 => 0.76, 11 => 0.60, 12 => 0.51][$index] ?? 0.72,
        'base' => $index === 12 ? 30000 : 14000,
    ], range(1, 12));
    $units[11]['ceiling'] = 26000;

    $weakest = pricer()->price(scenario($units))->forUnitOn('unit-12', new DateTimeImmutable('2026-09-19'));

    expect($weakest->recommendedPriceCents)->toBe(26000)
        ->and($weakest->reason)->toBe('-13%: 8 of 12 booked, target 10 by 8 days out, leader budget 2, weakest recent occupancy in the group. Limited to the ceiling price.');
});

it('says so when the floor price lifts a price leader above its base price', function () {
    $recommendations = pricer()->price(scenario([
        ['id' => 'booked', 'status' => 'booked'],
        ['id' => 'strong', 'status' => 'available', 'occupancy' => 0.9],
        ['id' => 'weak', 'status' => 'available', 'occupancy' => 0.4, 'base' => 9000, 'floor' => 9500],
    ]));

    $weak = $recommendations->forUnitOn('weak', new DateTimeImmutable('2026-09-19'));

    expect($weak->recommendedPriceCents)->toBe(9500)
        ->and($weak->reason)->toBe('Raised to the floor price: 1 of 3 booked, target 3 by 8 days out, leader budget 2, weakest recent occupancy in the group.');
});

it('holds a unit priced below its floor at the floor and says so', function () {
    $snapshot = twelveApartments(10);
    $units = array_map(fn ($unit) => [
        'id' => $unit->id,
        'status' => $unit->nights[0]->status->value,
        'occupancy' => $unit->trailingOccupancy,
        'base' => $unit->id === 'unit-12' ? 9000 : $unit->nights[0]->basePriceCents,
    ], $snapshot->units);

    $held = pricer()->price(scenario($units))->forUnitOn('unit-12', new DateTimeImmutable('2026-09-19'));

    expect($held->recommendedPriceCents)->toBe(9500)
        ->and($held->reason)->toBe('Held: 10 of 12 booked, target 10 by 8 days out, on pace. Limited to the floor price.');
});

it('describes nights that are already here as today', function () {
    $recommendations = pricer()->price(scenario([
        ['id' => 'booked', 'status' => 'booked'],
        ['id' => 'strong', 'status' => 'available', 'occupancy' => 0.9],
        ['id' => 'weak', 'status' => 'available', 'occupancy' => 0.4],
    ], leadTimeDays: 0));

    expect($recommendations->forUnitOn('weak', new DateTimeImmutable('2026-09-19'))->reason)
        ->toBe('-15%: 1 of 3 booked, target 3 by today, leader budget 2, weakest recent occupancy in the group.');
});

it('holds a group whose units are all blocked', function () {
    $recommendations = pricer()->price(scenario([
        ['id' => 'first', 'status' => 'blocked'],
        ['id' => 'second', 'status' => 'blocked'],
        ['id' => 'third', 'status' => 'blocked'],
    ]));
    $saturday = new DateTimeImmutable('2026-09-19');

    expect($recommendations->assessmentFor('downtown-1br', $saturday)->mode)->toBe(PricingMode::Hold)
        ->and($recommendations->assessmentFor('downtown-1br', $saturday)->sellableUnits())->toBe(0)
        ->and($recommendations->forGroupOn('downtown-1br', $saturday))->each(fn ($recommendation) => $recommendation->rule->toBe(PricingRule::BlockedNight));
});

it('skips units that have no night on a date while still counting them in the group', function () {
    $data = snapshotData();
    $data['units'] = [
        sampleUnit(['id' => 'first', 'nights' => [['date' => '2026-09-19', 'status' => 'available', 'base_price_cents' => 14000], ['date' => '2026-09-20', 'status' => 'available', 'base_price_cents' => 12000]]]),
        sampleUnit(['id' => 'second', 'nights' => [['date' => '2026-09-19', 'status' => 'booked', 'base_price_cents' => 14000], ['date' => '2026-09-20', 'status' => 'available', 'base_price_cents' => 12000]]]),
        sampleUnit(['id' => 'third', 'nights' => [['date' => '2026-09-19', 'status' => 'available', 'base_price_cents' => 14000]]]),
    ];

    $recommendations = pricer()->price(PortfolioSnapshot::fromArray($data));
    $sunday = new DateTimeImmutable('2026-09-20');

    expect($recommendations->assessmentFor('downtown-1br', $sunday)->groupSize)->toBe(3)
        ->and($recommendations->assessmentFor('downtown-1br', $sunday)->sellableUnits())->toBe(2)
        ->and(array_map(fn ($recommendation) => $recommendation->unitId, $recommendations->forGroupOn('downtown-1br', $sunday)))->toBe(['first', 'second']);
});

it('names the rank of every price leader in its reason', function (int $rank, string $ordinal) {
    $units = array_map(fn (int $index) => [
        'id' => sprintf('unit-%02d', $index),
        'status' => 'available',
        'occupancy' => $index / 100,
    ], range(1, 30));

    $recommendation = pricer(['leader_taper' => 0.0])->price(scenario($units))
        ->forUnitOn(sprintf('unit-%02d', $rank), new DateTimeImmutable('2026-09-19'));

    expect($recommendation->leaderRank)->toBe($rank)
        ->and($recommendation->reason)->toContain(", {$ordinal} recent occupancy in the group.");
})->with([
    [1, 'weakest'],
    [2, '2nd weakest'],
    [3, '3rd weakest'],
    [4, '4th weakest'],
    [11, '11th weakest'],
    [12, '12th weakest'],
    [13, '13th weakest'],
    [21, '21st weakest'],
    [22, '22nd weakest'],
    [23, '23rd weakest'],
]);

it('never labels a unit as a price leader with a zero discount', function () {
    $recommendations = pricer(['leader_taper' => 0.95])->price(twelveApartments(3))
        ->forGroupOn('downtown-1br', new DateTimeImmutable('2026-09-19'));

    $leaders = array_filter($recommendations, fn (Recommendation $recommendation) => $recommendation->isPriceLeader());
    $heldForWeakerUnits = array_filter($recommendations, fn (Recommendation $recommendation) => $recommendation->rule === PricingRule::HeldForWeakerUnits);

    expect($leaders)->not->toBeEmpty()
        ->each(fn ($leader) => $leader->assignedDiscount->toBeGreaterThan(0.0))
        ->and(count($leaders))->toBeLessThan(7)
        ->and($heldForWeakerUnits)->not->toBeEmpty();
});

it('decides modes at exactly the margin despite floating point noise', function (int $booked, int $leadTimeDays, PricingMode $mode) {
    $units = array_map(fn (int $index) => ['id' => "unit-{$index}", 'status' => $index <= $booked ? 'booked' : 'available'], range(1, 10));

    $recommendations = pricer()->price(scenario($units, night: '2026-09-24', leadTimeDays: $leadTimeDays));

    expect($recommendations->assessmentFor('downtown-1br', new DateTimeImmutable('2026-09-24'))->mode)->toBe($mode);
})->with([
    'shortfall of exactly the fill margin' => [9, 1, PricingMode::Fill],
    'occupancy of exactly the protect threshold' => [7, 14, PricingMode::Protect],
]);

it('orders its output by group, date and unit regardless of input order', function () {
    $nights = [['date' => '2026-09-19', 'status' => 'available', 'base_price_cents' => 14000], ['date' => '2026-09-20', 'status' => 'available', 'base_price_cents' => 12000]];
    $data = [
        'as_of' => '2026-09-11',
        'groups' => [['id' => 'zeta', 'name' => 'Zeta'], ['id' => 'alpha', 'name' => 'Alpha']],
        'units' => [
            sampleUnit(['id' => 'z-2', 'group_id' => 'zeta', 'nights' => array_reverse($nights)]),
            sampleUnit(['id' => 'a-2', 'group_id' => 'alpha', 'nights' => $nights]),
            sampleUnit(['id' => 'z-1', 'group_id' => 'zeta', 'nights' => $nights]),
            sampleUnit(['id' => 'a-1', 'group_id' => 'alpha', 'nights' => array_reverse($nights)]),
        ],
    ];

    $recommendations = pricer()->price(PortfolioSnapshot::fromArray($data));

    expect(array_map(fn ($assessment) => $assessment->groupId.' '.$assessment->date->format('Y-m-d'), $recommendations->assessments))
        ->toBe(['alpha 2026-09-19', 'alpha 2026-09-20', 'zeta 2026-09-19', 'zeta 2026-09-20'])
        ->and(array_map(fn ($recommendation) => $recommendation->unitId, $recommendations->recommendations))
        ->toBe(['a-1', 'a-2', 'a-1', 'a-2', 'z-1', 'z-2', 'z-1', 'z-2']);
});

it('says a discount is under 1% when the floor leaves the price just below its base', function () {
    $units = array_map(fn (int $index) => [
        'id' => sprintf('unit-%02d', $index),
        'status' => $index <= 8 ? 'booked' : 'available',
        'occupancy' => [9 => 0.85, 10 => 0.76, 11 => 0.60, 12 => 0.51][$index] ?? 0.72,
        'base' => $index === 12 ? 30000 : 14000,
    ], range(1, 12));
    $units[11]['floor'] = 29900;
    $units[11]['ceiling'] = 32000;

    $weakest = pricer()->price(scenario($units))->forUnitOn('unit-12', new DateTimeImmutable('2026-09-19'));

    expect($weakest->recommendedPriceCents)->toBe(29900)
        ->and($weakest->reason)->toBe('Lowered by less than 1%: 8 of 12 booked, target 10 by 8 days out, leader budget 2, weakest recent occupancy in the group. Limited to the floor price.');
});

it('explains held units when further discounts would round to 0%', function () {
    $held = pricer(['leader_taper' => 0.95])->price(twelveApartments(3))
        ->forUnitOn('unit-09', new DateTimeImmutable('2026-09-19'));

    expect($held->rule)->toBe(PricingRule::HeldForWeakerUnits)
        ->and($held->reason)->toBe('Held: 3 of 12 booked, target 10 by 8 days out, a further discount would round to 0%.');
});

it('can raise one unit discount when a weaker unit books while the group discounts less', function () {
    $saturday = new DateTimeImmutable('2026-09-19');

    $before = pricer()->price(twelveApartments(3));
    $after = pricer()->price(withNightBooked(twelveApartments(3), 'unit-12', $saturday));

    expect($before->forUnitOn('unit-11', $saturday)->assignedDiscount)->toBe(0.11)
        ->and($after->forUnitOn('unit-11', $saturday)->assignedDiscount)->toBe(0.15)
        ->and($before->assessmentFor('downtown-1br', $saturday)->leaderBudget)->toBe(7)
        ->and($after->assessmentFor('downtown-1br', $saturday)->leaderBudget)->toBe(6);
});

it('records the booking strength used for ranking on every recommendation', function () {
    $saturday = new DateTimeImmutable('2026-09-19');

    $recommendations = pricer()->price(scenario([
        ['id' => 'unit-a', 'status' => 'available', 'occupancy' => 0.5],
        ['id' => 'unit-b', 'status' => 'booked', 'occupancy' => 0.75],
        ['id' => 'unit-c', 'status' => 'available', 'occupancy' => 1.0],
        ['id' => 'unit-d', 'status' => 'available', 'occupancy' => 0.2, 'history' => 10],
    ]));

    expect($recommendations->forUnitOn('unit-a', $saturday)->bookingStrength)->toEqualWithDelta(0.6667, 0.0001)
        ->and($recommendations->forUnitOn('unit-b', $saturday)->bookingStrength)->toBe(1.0)
        ->and($recommendations->forUnitOn('unit-c', $saturday)->bookingStrength)->toEqualWithDelta(1.3333, 0.0001)
        ->and($recommendations->forUnitOn('unit-d', $saturday)->bookingStrength)->toBe(1.0);
});
