<?php

namespace App\Pricing\Data;

use App\Pricing\Enums\NightStatus;
use App\Pricing\Exceptions\InvalidPortfolioSnapshot;
use DateTimeImmutable;
use DateTimeZone;

final readonly class PortfolioSnapshot
{
    public DateTimeImmutable $asOf;

    /**
     * @param  list<Group>  $groups
     * @param  list<Unit>  $units
     */
    public function __construct(
        DateTimeImmutable $asOf,
        public array $groups,
        public array $units,
    ) {
        $this->asOf = new DateTimeImmutable($asOf->format('Y-m-d'), new DateTimeZone('UTC'));

        $groupIds = array_map(fn (Group $group): string => $group->id, $groups);

        if (($duplicate = self::firstDuplicate($groupIds)) !== null) {
            throw new InvalidPortfolioSnapshot("Group ID [{$duplicate}] is used more than once.");
        }

        if (($duplicate = self::firstDuplicate(array_map(fn (Unit $unit): string => $unit->id, $units))) !== null) {
            throw new InvalidPortfolioSnapshot("Unit ID [{$duplicate}] is used more than once.");
        }

        foreach ($units as $unit) {
            if (! in_array($unit->groupId, $groupIds, true)) {
                throw new InvalidPortfolioSnapshot("Unit [{$unit->id}] references unknown group [{$unit->groupId}].");
            }

            foreach ($unit->nights as $night) {
                if ($night->date < $this->asOf) {
                    throw new InvalidPortfolioSnapshot("Unit [{$unit->id}] has a night on {$night->date->format('Y-m-d')}, before the snapshot date {$this->asOf->format('Y-m-d')}.");
                }
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::dateFrom($data, 'as_of', 'snapshot'),
            array_map(self::groupFrom(...), self::listFrom($data, 'groups', 'snapshot')),
            array_map(self::unitFrom(...), self::listFrom($data, 'units', 'snapshot')),
        );
    }

    /**
     * @return list<Unit>
     */
    public function unitsInGroup(string $groupId): array
    {
        return array_values(array_filter($this->units, fn (Unit $unit): bool => $unit->groupId === $groupId));
    }

    private static function groupFrom(mixed $group): Group
    {
        $group = self::arrayFrom($group, 'group');

        return new Group(
            self::stringFrom($group, 'id', 'group'),
            self::stringFrom($group, 'name', 'group'),
        );
    }

    private static function unitFrom(mixed $unit): Unit
    {
        $unit = self::arrayFrom($unit, 'unit');
        $id = self::stringFrom($unit, 'id', 'unit');
        $context = "unit [{$id}]";

        return new Unit(
            id: $id,
            groupId: self::stringFrom($unit, 'group_id', $context),
            name: self::stringFrom($unit, 'name', $context),
            floorPriceCents: self::intFrom($unit, 'floor_price_cents', $context),
            ceilingPriceCents: self::intFrom($unit, 'ceiling_price_cents', $context),
            trailingOccupancy: self::floatFrom($unit, 'trailing_occupancy', $context),
            historyNights: self::intFrom($unit, 'history_nights', $context),
            nights: array_map(fn (mixed $night): Night => self::nightFrom($night, $context), self::listFrom($unit, 'nights', $context)),
        );
    }

    private static function nightFrom(mixed $night, string $unitContext): Night
    {
        $context = "{$unitContext} night";
        $night = self::arrayFrom($night, $context);
        $date = self::dateFrom($night, 'date', $context);
        $statusValue = self::stringFrom($night, 'status', $context);
        $basePriceCents = self::intFrom($night, 'base_price_cents', $context);

        if ($basePriceCents <= 0) {
            throw new InvalidPortfolioSnapshot("The {$context} on {$date->format('Y-m-d')} needs a positive base_price_cents, got {$basePriceCents}.");
        }

        return new Night(
            $date,
            NightStatus::tryFrom($statusValue)
                ?? throw new InvalidPortfolioSnapshot("The {$context} status must be booked, available or blocked, got [{$statusValue}]."),
            $basePriceCents,
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function arrayFrom(mixed $value, string $context): array
    {
        if (! is_array($value)) {
            throw new InvalidPortfolioSnapshot("Each {$context} must be an object.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<mixed>
     */
    private static function listFrom(array $data, string $key, string $context): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidPortfolioSnapshot("The {$context} {$key} must be a list.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function stringFrom(array $data, string $key, string $context): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidPortfolioSnapshot("The {$context} {$key} must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function intFrom(array $data, string $key, string $context): int
    {
        $value = $data[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidPortfolioSnapshot("The {$context} {$key} must be an integer.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function floatFrom(array $data, string $key, string $context): float
    {
        $value = $data[$key] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidPortfolioSnapshot("The {$context} {$key} must be a number.");
        }

        return (float) $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function dateFrom(array $data, string $key, string $context): DateTimeImmutable
    {
        $value = self::stringFrom($data, $key, $context);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidPortfolioSnapshot("The {$context} {$key} must be a Y-m-d date, got [{$value}].");
        }

        return $date;
    }

    /**
     * @param  list<string>  $values
     */
    private static function firstDuplicate(array $values): ?string
    {
        $seen = [];

        foreach ($values as $value) {
            if (isset($seen[$value])) {
                return $value;
            }

            $seen[$value] = true;
        }

        return null;
    }
}
