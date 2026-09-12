<?php

namespace App\Pricing;

use App\Pricing\Data\Night;
use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\Unit;
use App\Pricing\Enums\DayType;
use App\Pricing\Enums\NightStatus;
use App\Pricing\Exceptions\InvalidDemoScenario;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class DemoScenario
{
    public const MAX_LEAD_TIME_DAYS = 60;

    public function __construct(
        private PortfolioSnapshot $fixture,
        private string $weekendNight,
        private string $weekdayNight,
    ) {}

    public function unitCount(): int
    {
        return count($this->fixture->units);
    }

    public function nightFor(DayType $dayType): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $dayType === DayType::Weekend ? $this->weekendNight : $this->weekdayNight,
            new DateTimeZone('UTC'),
        );
    }

    public function build(int $bookedUnits, int $leadTimeDays, DayType $dayType): PortfolioSnapshot
    {
        if ($bookedUnits < 0 || $bookedUnits > $this->unitCount()) {
            throw new InvalidDemoScenario("Booked units must be between 0 and {$this->unitCount()}, got {$bookedUnits}.");
        }

        if ($leadTimeDays < 0 || $leadTimeDays > self::MAX_LEAD_TIME_DAYS) {
            throw new InvalidDemoScenario('Lead time must be between 0 and '.self::MAX_LEAD_TIME_DAYS." days, got {$leadTimeDays}.");
        }

        $night = $this->nightFor($dayType);
        $units = $this->fixture->units;
        usort($units, fn (Unit $first, Unit $second): int => strcmp($first->id, $second->id));

        $scenarioUnits = [];

        foreach ($units as $index => $unit) {
            $fixtureNight = $unit->nightOn($night)
                ?? throw new InvalidDemoScenario("Unit [{$unit->id}] has no night on {$night->format('Y-m-d')} in the fixture.");

            $scenarioUnits[] = new Unit(
                id: $unit->id,
                groupId: $unit->groupId,
                name: $unit->name,
                floorPriceCents: $unit->floorPriceCents,
                ceilingPriceCents: $unit->ceilingPriceCents,
                trailingOccupancy: $unit->trailingOccupancy,
                historyNights: $unit->historyNights,
                nights: [new Night($night, $index < $bookedUnits ? NightStatus::Booked : NightStatus::Available, $fixtureNight->basePriceCents)],
            );
        }

        return new PortfolioSnapshot($night->sub(new DateInterval("P{$leadTimeDays}D")), $this->fixture->groups, $scenarioUnits);
    }
}
