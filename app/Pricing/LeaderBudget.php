<?php

namespace App\Pricing;

use App\Pricing\Enums\BudgetRounding;

final readonly class LeaderBudget
{
    public function __construct(private BudgetRounding $rounding) {}

    public function targetBooked(float $target, int $sellableUnits): int
    {
        // Strip floating point noise first, so 0.07 x 100 counts as exactly 7 and does not round up to 8.
        $exact = round($target * $sellableUnits, 9);

        return (int) match ($this->rounding) {
            BudgetRounding::Ceil => ceil($exact),
            BudgetRounding::Round => round($exact),
        };
    }

    public function forNight(float $target, int $sellableUnits, int $bookedUnits, int $availableUnits): int
    {
        return min(max($this->targetBooked($target, $sellableUnits) - $bookedUnits, 0), $availableUnits);
    }
}
