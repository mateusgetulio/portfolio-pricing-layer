<?php

namespace App\Pricing\Data;

use App\Pricing\Exceptions\InvalidPortfolioSnapshot;
use DateTimeImmutable;

final readonly class Unit
{
    /**
     * @param  list<Night>  $nights
     */
    public function __construct(
        public string $id,
        public string $groupId,
        public string $name,
        public int $floorPriceCents,
        public int $ceilingPriceCents,
        public float $trailingOccupancy,
        public int $historyNights,
        public array $nights,
    ) {
        if (trim($id) === '') {
            throw new InvalidPortfolioSnapshot('Unit IDs cannot be empty.');
        }

        if ($floorPriceCents <= 0 || $ceilingPriceCents <= 0) {
            throw new InvalidPortfolioSnapshot("Unit [{$id}] needs positive floor and ceiling prices.");
        }

        if ($floorPriceCents > $ceilingPriceCents) {
            throw new InvalidPortfolioSnapshot("Unit [{$id}] has a floor price above its ceiling price.");
        }

        if ($trailingOccupancy < 0.0 || $trailingOccupancy > 1.0) {
            throw new InvalidPortfolioSnapshot("Unit [{$id}] trailing occupancy must be between 0 and 1, got {$trailingOccupancy}.");
        }

        if ($historyNights < 0) {
            throw new InvalidPortfolioSnapshot("Unit [{$id}] cannot have negative nights of history.");
        }

        $dates = array_map(fn (Night $night): string => $night->date->format('Y-m-d'), $nights);

        if (count($dates) !== count(array_unique($dates))) {
            throw new InvalidPortfolioSnapshot("Unit [{$id}] has more than one entry for the same night.");
        }
    }

    public function hasEnoughHistory(int $minimumNights): bool
    {
        return $this->historyNights >= $minimumNights;
    }

    public function nightOn(DateTimeImmutable $date): ?Night
    {
        foreach ($this->nights as $night) {
            if ($night->date->format('Y-m-d') === $date->format('Y-m-d')) {
                return $night;
            }
        }

        return null;
    }
}
