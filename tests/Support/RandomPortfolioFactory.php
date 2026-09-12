<?php

namespace Tests\Support;

use App\Pricing\Data\Group;
use App\Pricing\Data\Night;
use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\Unit;
use App\Pricing\Enums\NightStatus;
use DateTimeImmutable;
use DateTimeZone;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class RandomPortfolioFactory
{
    private Randomizer $random;

    public function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public function make(): PortfolioSnapshot
    {
        $asOf = new DateTimeImmutable('2026-09-11', new DateTimeZone('UTC'));
        $numericIds = $this->random->getInt(0, 1) === 1;
        $groups = [];
        $units = [];

        foreach (range(1, $this->random->getInt(1, 3)) as $groupNumber) {
            $group = new Group("group-{$groupNumber}", "Group {$groupNumber}");
            $groups[] = $group;
            $dates = $this->nightDates($asOf);

            $bookedShare = $this->random->getInt(0, 6);

            foreach (range(1, $this->random->getInt(1, 24)) as $unitNumber) {
                $id = $numericIds && $groupNumber === 1 ? (string) $unitNumber : "{$group->id}-unit-{$unitNumber}";
                $units[] = $this->unit($id, $group, $dates, $bookedShare);
            }
        }

        return new PortfolioSnapshot($asOf, $groups, $units);
    }

    private function nightDates(DateTimeImmutable $asOf): array
    {
        $offsets = $this->random->pickArrayKeys(range(0, 75), $this->random->getInt(1, 3));

        return array_map(fn (int $days): DateTimeImmutable => $asOf->modify("+{$days} days"), $offsets);
    }

    private function unit(string $id, Group $group, array $dates, int $bookedShare): Unit
    {
        $typicalPriceCents = $this->random->getInt(1, 400) * 100;
        $nights = [];

        foreach ($dates as $date) {
            if ($this->random->getInt(1, 10) === 1) {
                continue;
            }

            $basePriceCents = max(100, (int) round($typicalPriceCents * $this->random->getInt(80, 120) / 10000) * 100);

            if ($this->random->getInt(1, 4) === 1) {
                $basePriceCents += $this->random->getInt(1, 99);
            }

            $nights[] = new Night($date, $this->status($bookedShare), $basePriceCents);
        }

        $prices = array_map(fn (Night $night): int => $night->basePriceCents, $nights) ?: [$typicalPriceCents];
        $floorPriceCents = $this->random->getInt(1, 5) === 1 && min($prices) >= 200
            ? min($prices) - 100
            : max(100, (int) floor(min($prices) * $this->random->getInt(30, 100) / 10000) * 100);
        $ceilingPriceCents = (int) ceil(max($prices) * $this->random->getInt(100, 160) / 10000) * 100;

        return new Unit(
            id: $id,
            groupId: $group->id,
            name: $id,
            floorPriceCents: $floorPriceCents,
            ceilingPriceCents: $ceilingPriceCents,
            trailingOccupancy: $this->random->getInt(0, 100) / 100,
            historyNights: $this->random->getInt(0, 120),
            nights: $nights,
        );
    }

    private function status(int $bookedShare): NightStatus
    {
        $roll = $this->random->getInt(1, 10);

        return match (true) {
            $roll === 1 => NightStatus::Blocked,
            $roll <= 1 + $bookedShare => NightStatus::Booked,
            default => NightStatus::Available,
        };
    }
}
