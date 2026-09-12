<?php

namespace App\Pricing\Data;

use App\Pricing\Enums\NightStatus;
use App\Pricing\Exceptions\InvalidPortfolioSnapshot;
use DateTimeImmutable;
use DateTimeZone;

final readonly class Night
{
    public DateTimeImmutable $date;

    public function __construct(
        DateTimeImmutable $date,
        public NightStatus $status,
        public int $basePriceCents,
    ) {
        $this->date = new DateTimeImmutable($date->format('Y-m-d'), new DateTimeZone('UTC'));

        if ($basePriceCents <= 0) {
            throw new InvalidPortfolioSnapshot("Base prices must be positive, got {$basePriceCents} on {$this->date->format('Y-m-d')}.");
        }
    }
}
