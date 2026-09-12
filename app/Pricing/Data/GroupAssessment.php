<?php

namespace App\Pricing\Data;

use App\Pricing\Enums\PricingMode;
use DateTimeImmutable;

final readonly class GroupAssessment
{
    public function __construct(
        public string $groupId,
        public int $groupSize,
        public DateTimeImmutable $date,
        public int $leadTimeDays,
        public int $bookedUnits,
        public int $availableUnits,
        public float $occupancy,
        public float $target,
        public int $targetBooked,
        public int $leaderBudget,
        public PricingMode $mode,
        public float $discountRate,
    ) {}

    public function sellableUnits(): int
    {
        return $this->bookedUnits + $this->availableUnits;
    }
}
