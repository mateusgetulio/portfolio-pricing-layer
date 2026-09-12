<?php

use App\Pricing\Data\Unit;
use App\Pricing\LeaderSelector;

it('picks the weakest available units first', function () {
    $units = [makeUnit('strong', 0.9), makeUnit('weak', 0.3), makeUnit('average', 0.6)];

    $leaders = (new LeaderSelector)->select($units, ['strong' => 1.5, 'weak' => 0.5, 'average' => 1.0], 2);

    expect(array_map(fn (Unit $unit) => $unit->id, $leaders))->toBe(['weak', 'average']);
});

it('selects nobody without a leader budget', function () {
    $leaders = (new LeaderSelector)->select([makeUnit('weak', 0.3)], ['weak' => 0.5], 0);

    expect($leaders)->toBe([]);
});

it('selects every unit when the budget covers all of them', function () {
    $units = [makeUnit('strong', 0.9), makeUnit('weak', 0.3)];

    $leaders = (new LeaderSelector)->select($units, ['strong' => 1.5, 'weak' => 0.5], 5);

    expect(array_map(fn (Unit $unit) => $unit->id, $leaders))->toBe(['weak', 'strong']);
});

it('breaks ties by unit ID in string order', function () {
    $units = [makeUnit('9', 0.5), makeUnit('10', 0.5)];

    $leaders = (new LeaderSelector)->select($units, ['9' => 1.0, '10' => 1.0], 1);

    expect($leaders[0]->id)->toBe('10');
});
