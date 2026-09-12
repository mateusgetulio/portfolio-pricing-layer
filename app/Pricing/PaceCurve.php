<?php

namespace App\Pricing;

use App\Pricing\Data\PacePoint;
use App\Pricing\Enums\DayType;
use App\Pricing\Exceptions\InvalidPaceCurve;

final readonly class PaceCurve
{
    /**
     * @param  list<PacePoint>  $points
     */
    public function __construct(public array $points)
    {
        if (count($points) < 2) {
            throw new InvalidPaceCurve('A pace curve needs at least two points.');
        }

        foreach ($points as $index => $point) {
            $this->ensureValidTargets($point);

            if ($index > 0) {
                $this->ensureFollows($points[$index - 1], $point);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $points
     */
    public static function fromArray(array $points): self
    {
        if (! array_is_list($points)) {
            throw new InvalidPaceCurve('Pace curve points must be a list.');
        }

        return new self(array_map(self::pointFrom(...), $points));
    }

    public function targetFor(int $leadTimeDays, DayType $dayType): float
    {
        $first = $this->points[0];
        $last = $this->points[count($this->points) - 1];

        if ($leadTimeDays <= $first->leadTimeDays) {
            return $first->targetFor($dayType);
        }

        foreach ($this->points as $index => $upper) {
            if ($index === 0 || $leadTimeDays > $upper->leadTimeDays) {
                continue;
            }

            $lower = $this->points[$index - 1];
            $progress = ($leadTimeDays - $lower->leadTimeDays) / ($upper->leadTimeDays - $lower->leadTimeDays);

            return $lower->targetFor($dayType) + ($upper->targetFor($dayType) - $lower->targetFor($dayType)) * $progress;
        }

        return $last->targetFor($dayType);
    }

    private static function pointFrom(mixed $point): PacePoint
    {
        $leadTimeDays = is_array($point) ? ($point['lead_time_days'] ?? null) : null;
        $weekdayTarget = is_array($point) ? ($point['weekday'] ?? null) : null;
        $weekendTarget = is_array($point) ? ($point['weekend'] ?? null) : null;

        if (! is_int($leadTimeDays) || ! is_numeric($weekdayTarget) || ! is_numeric($weekendTarget)) {
            throw new InvalidPaceCurve('Each pace curve point needs an integer lead_time_days and numeric weekday and weekend targets.');
        }

        return new PacePoint($leadTimeDays, (float) $weekdayTarget, (float) $weekendTarget);
    }

    private function ensureValidTargets(PacePoint $point): void
    {
        if ($point->leadTimeDays < 0) {
            throw new InvalidPaceCurve("Pace curve lead times cannot be negative, got {$point->leadTimeDays}.");
        }

        foreach (DayType::cases() as $dayType) {
            $target = $point->targetFor($dayType);

            if ($target <= 0.0 || $target > 1.0) {
                throw new InvalidPaceCurve("The {$dayType->value} target at {$point->leadTimeDays} days must be greater than 0 and at most 1, got {$target}.");
            }
        }
    }

    private function ensureFollows(PacePoint $previous, PacePoint $point): void
    {
        if ($point->leadTimeDays <= $previous->leadTimeDays) {
            throw new InvalidPaceCurve('Pace curve lead times must be strictly increasing.');
        }

        foreach (DayType::cases() as $dayType) {
            if ($point->targetFor($dayType) > $previous->targetFor($dayType)) {
                throw new InvalidPaceCurve("The {$dayType->value} target cannot increase as lead time grows ({$previous->leadTimeDays} to {$point->leadTimeDays} days).");
            }
        }
    }
}
