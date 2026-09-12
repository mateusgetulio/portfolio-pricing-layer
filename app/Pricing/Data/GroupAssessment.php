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

    public function progress(): string
    {
        $daysOut = match (true) {
            $this->leadTimeDays <= 0 => 'today',
            $this->leadTimeDays === 1 => '1 day out',
            default => "{$this->leadTimeDays} days out",
        };

        return "{$this->bookedUnits} of {$this->sellableUnits()} booked, target {$this->targetBooked} by {$daysOut}";
    }

    public function sellableUnits(): int
    {
        return $this->bookedUnits + $this->availableUnits;
    }
}
