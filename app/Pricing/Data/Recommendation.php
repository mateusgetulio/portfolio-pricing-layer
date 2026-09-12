<?php

namespace App\Pricing\Data;

use App\Pricing\Enums\NightStatus;
use App\Pricing\Enums\PricingMode;
use App\Pricing\Enums\PricingRule;
use DateTimeImmutable;

final readonly class Recommendation
{
    public function __construct(
        public string $unitId,
        public string $groupId,
        public DateTimeImmutable $date,
        public NightStatus $status,
        public int $basePriceCents,
        public int $recommendedPriceCents,
        public float $assignedDiscount,
        public PricingMode $mode,
        public PricingRule $rule,
        public ?int $leaderRank,
        public float $bookingStrength,
        public string $reason,
    ) {}

    public function discount(): float
    {
        return 1 - $this->recommendedPriceCents / $this->basePriceCents;
    }

    public function isPriceLeader(): bool
    {
        return $this->rule === PricingRule::PriceLeader;
    }
}
