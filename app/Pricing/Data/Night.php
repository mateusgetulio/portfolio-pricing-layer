<?php

namespace App\Pricing\Data;

use App\Pricing\Enums\NightStatus;
use App\Pricing\Exceptions\InvalidPortfolioSnapshot;
use DateTimeImmutable;

final readonly class Night
{
    public function __construct(
        public DateTimeImmutable $date,
        public NightStatus $status,
        public int $basePriceCents,
    ) {
        if ($basePriceCents <= 0) {
            throw new InvalidPortfolioSnapshot("Base prices must be positive, got {$basePriceCents} on {$date->format('Y-m-d')}.");
        }
    }
}
