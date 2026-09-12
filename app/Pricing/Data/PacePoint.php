<?php

namespace App\Pricing\Data;

use App\Pricing\Enums\DayType;

final readonly class PacePoint
{
    public function __construct(
        public int $leadTimeDays,
        public float $weekdayTarget,
        public float $weekendTarget,
    ) {}

    public function targetFor(DayType $dayType): float
    {
        return match ($dayType) {
            DayType::Weekday => $this->weekdayTarget,
            DayType::Weekend => $this->weekendTarget,
        };
    }
}
