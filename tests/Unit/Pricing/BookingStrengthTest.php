<?php

use App\Pricing\BookingStrength;

it('measures each unit against the mean occupancy of its group', function () {
    $strengths = (new BookingStrength(30))->forUnits([
        makeUnit('a', 0.9),
        makeUnit('b', 0.6),
        makeUnit('c', 0.3),
        makeUnit('d', 0.6),
    ]);

    expect($strengths['a'])->toEqualWithDelta(1.5, 0.0001)
        ->and($strengths['b'])->toEqualWithDelta(1.0, 0.0001)
        ->and($strengths['c'])->toEqualWithDelta(0.5, 0.0001);
});

it('leaves units without enough history out of the group mean and scores them as average', function () {
    $strengths = (new BookingStrength(30))->forUnits([
        makeUnit('a', 0.9),
        makeUnit('b', 0.3),
        makeUnit('new', 0.0, historyNights: 10),
    ]);

    expect($strengths['a'])->toEqualWithDelta(1.5, 0.0001)
        ->and($strengths['b'])->toEqualWithDelta(0.5, 0.0001)
        ->and($strengths['new'])->toBe(1.0);
});

it('treats every unit as average when no unit has enough history', function () {
    $strengths = (new BookingStrength(30))->forUnits([
        makeUnit('a', 0.9, historyNights: 5),
        makeUnit('b', 0.3, historyNights: 5),
    ]);

    expect($strengths)->toBe(['a' => 1.0, 'b' => 1.0]);
});

it('treats every unit as average when the group has no occupancy', function () {
    $strengths = (new BookingStrength(30))->forUnits([
        makeUnit('a', 0.0),
        makeUnit('b', 0.0),
    ]);

    expect($strengths)->toBe(['a' => 1.0, 'b' => 1.0]);
});

it('does not depend on the order of units', function () {
    $units = [makeUnit('a', 0.9), makeUnit('b', 0.6), makeUnit('c', 0.3)];
    $strength = new BookingStrength(30);

    $inOrder = $strength->forUnits($units);
    $reversed = $strength->forUnits(array_reverse($units));
    ksort($inOrder);
    ksort($reversed);

    expect($reversed)->toBe($inOrder);
});
