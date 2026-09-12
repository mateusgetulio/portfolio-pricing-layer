<?php

namespace App\Pricing;

use App\Pricing\Data\Unit;

final readonly class BookingStrength
{
    public function __construct(private int $minimumHistoryNights) {}

    /**
     * @param  list<Unit>  $units
     * @return array<array-key, float>
     */
    public function forUnits(array $units): array
    {
        $experienced = array_filter($units, fn (Unit $unit): bool => $unit->hasEnoughHistory($this->minimumHistoryNights));

        $meanOccupancy = $experienced === []
            ? 0.0
            : array_sum(array_map(fn (Unit $unit): float => $unit->trailingOccupancy, $experienced)) / count($experienced);

        $strengths = [];

        foreach ($units as $unit) {
            $strengths[$unit->id] = $meanOccupancy > 0.0 && $unit->hasEnoughHistory($this->minimumHistoryNights)
                ? $unit->trailingOccupancy / $meanOccupancy
                : 1.0;
        }

        return $strengths;
    }
}
