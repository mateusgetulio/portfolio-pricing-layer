<?php

namespace App\Pricing\Enums;

use DateTimeImmutable;

enum DayType: string
{
    case Weekday = 'weekday';
    case Weekend = 'weekend';

    public static function forNight(DateTimeImmutable $night): self
    {
        return in_array($night->format('l'), ['Friday', 'Saturday'], true)
            ? self::Weekend
            : self::Weekday;
    }
}
