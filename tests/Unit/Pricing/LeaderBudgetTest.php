<?php

use App\Pricing\Enums\BudgetRounding;
use App\Pricing\LeaderBudget;

it('counts the bookings still needed to reach the target', function (float $target, int $booked, int $available, int $budget) {
    $leaderBudget = new LeaderBudget(BudgetRounding::Ceil);

    expect($leaderBudget->forNight($target, $booked + $available, $booked, $available))->toBe($budget);
})->with([
    'worked example' => [0.8286, 8, 4, 2],
    'far behind pace' => [0.8286, 3, 9, 7],
    'exactly on target' => [0.8286, 10, 2, 0],
    'ahead of target' => [0.8286, 11, 1, 0],
    'nothing sellable' => [0.8286, 0, 0, 0],
]);

it('rounds the target up by default and to the nearest unit when configured', function () {
    expect((new LeaderBudget(BudgetRounding::Ceil))->targetBooked(0.72, 10))->toBe(8)
        ->and((new LeaderBudget(BudgetRounding::Round))->targetBooked(0.72, 10))->toBe(7);
});

it('ignores floating point noise when rounding the target up', function () {
    expect(0.07 * 100)->not->toBe(7.0)
        ->and((new LeaderBudget(BudgetRounding::Ceil))->targetBooked(0.07, 100))->toBe(7);
});
