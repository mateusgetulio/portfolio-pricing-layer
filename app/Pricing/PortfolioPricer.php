<?php

namespace App\Pricing;

use App\Pricing\Data\Group;
use App\Pricing\Data\GroupAssessment;
use App\Pricing\Data\Night;
use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\PricingConfig;
use App\Pricing\Data\Recommendation;
use App\Pricing\Data\RecommendationSet;
use App\Pricing\Data\Unit;
use App\Pricing\Enums\DayType;
use App\Pricing\Enums\NightStatus;
use App\Pricing\Enums\PricingMode;
use App\Pricing\Enums\PricingRule;
use DateTimeImmutable;

final readonly class PortfolioPricer
{
    private BookingStrength $bookingStrength;

    private LeaderBudget $leaderBudget;

    private GroupDiscountRate $groupDiscountRate;

    private LeaderSelector $leaderSelector;

    public function __construct(private PricingConfig $config)
    {
        $this->bookingStrength = new BookingStrength($config->minHistoryNights);
        $this->leaderBudget = new LeaderBudget($config->budgetRounding);
        $this->groupDiscountRate = new GroupDiscountRate($config);
        $this->leaderSelector = new LeaderSelector;
    }

    public function price(PortfolioSnapshot $snapshot): RecommendationSet
    {
        $groups = $snapshot->groups;
        usort($groups, fn (Group $first, Group $second): int => strcmp($first->id, $second->id));

        $assessments = [];
        $recommendations = [];

        foreach ($groups as $group) {
            $units = $snapshot->unitsInGroup($group->id);
            usort($units, fn (Unit $first, Unit $second): int => strcmp($first->id, $second->id));
            $strengths = $this->bookingStrength->forUnits($units);

            foreach ($this->nightDates($units) as $date) {
                $assessment = $this->assess($snapshot, $group, $units, $date);
                $assessments[] = $assessment;

                foreach ($this->recommendNight($assessment, $units, $strengths) as $recommendation) {
                    $recommendations[] = $recommendation;
                }
            }
        }

        return new RecommendationSet($assessments, $recommendations);
    }

    /**
     * @param  list<Unit>  $units
     */
    private function assess(PortfolioSnapshot $snapshot, Group $group, array $units, DateTimeImmutable $date): GroupAssessment
    {
        $booked = $this->countNights($units, $date, NightStatus::Booked);
        $available = $this->countNights($units, $date, NightStatus::Available);
        $sellable = $booked + $available;
        $leadTimeDays = $snapshot->leadTimeDays($date);
        $target = $this->config->paceCurve->targetFor($leadTimeDays, DayType::forNight($date));
        $occupancy = $sellable > 0 ? $booked / $sellable : 0.0;
        $leaderBudget = $this->leaderBudget->forNight($target, $sellable, $booked, $available);
        $shortfall = $target - $occupancy;

        // Compared at nine decimals so a gap of exactly the margin is not lost to floating point noise.
        $mode = match (true) {
            count($units) < $this->config->minGroupSize => PricingMode::PassThrough,
            round($occupancy, 9) >= round($target + $this->config->protectMargin, 9) => PricingMode::Protect,
            round($shortfall, 9) >= round($this->config->fillMargin, 9) && $leaderBudget >= 1 => PricingMode::Fill,
            default => PricingMode::Hold,
        };

        return new GroupAssessment(
            groupId: $group->id,
            groupSize: count($units),
            date: $date,
            leadTimeDays: $leadTimeDays,
            bookedUnits: $booked,
            availableUnits: $available,
            occupancy: $occupancy,
            target: $target,
            targetBooked: $this->leaderBudget->targetBooked($target, $sellable),
            leaderBudget: $mode === PricingMode::Fill ? $leaderBudget : 0,
            mode: $mode,
            discountRate: $mode === PricingMode::Fill ? $this->groupDiscountRate->forShortfall($shortfall, $leadTimeDays) : 0.0,
        );
    }

    /**
     * @param  list<Unit>  $units
     * @param  array<array-key, float>  $strengths
     * @return list<Recommendation>
     */
    private function recommendNight(GroupAssessment $assessment, array $units, array $strengths): array
    {
        $availableUnits = array_values(array_filter(
            $units,
            fn (Unit $unit): bool => $unit->nightOn($assessment->date)?->status === NightStatus::Available,
        ));

        $discounts = $this->leaderDiscounts($assessment->discountRate, $assessment->leaderBudget);
        $leaders = $this->leaderSelector->select($availableUnits, $strengths, count($discounts));

        $recommendations = [];

        foreach ($units as $unit) {
            $night = $unit->nightOn($assessment->date);

            if ($night === null) {
                continue;
            }

            $rank = array_search($unit, $leaders, true);

            $recommendations[] = $rank === false
                ? $this->unchanged($assessment, $unit, $night)
                : $this->priceLeader($assessment, $unit, $night, $rank, $discounts[$rank]);
        }

        return $recommendations;
    }

    /**
     * @return list<float>
     */
    private function leaderDiscounts(float $groupDiscountRate, int $leaderBudget): array
    {
        $discounts = [];
        $previous = $groupDiscountRate;

        for ($rank = 0; $rank < $leaderBudget; $rank++) {
            $previous = min($previous, round($groupDiscountRate * (1 - $this->config->leaderTaper) ** $rank, 2));

            if ($previous <= 0.0) {
                break;
            }

            $discounts[] = $previous;
        }

        return $discounts;
    }

    private function unchanged(GroupAssessment $assessment, Unit $unit, Night $night): Recommendation
    {
        [$rule, $reason] = match (true) {
            $night->status === NightStatus::Booked => [PricingRule::BookedNight, 'Booked night, never repriced.'],
            $night->status === NightStatus::Blocked => [PricingRule::BlockedNight, 'Blocked night, never repriced.'],
            $assessment->mode === PricingMode::PassThrough => [PricingRule::SmallGroup, "Unchanged: a group of {$assessment->groupSize} units is too small for portfolio pricing."],
            $assessment->mode === PricingMode::Protect => [PricingRule::AheadOfPace, "Protected: {$assessment->progress()}, ahead of pace."],
            $assessment->mode === PricingMode::Hold => [PricingRule::OnPace, "Held: {$assessment->progress()}, on pace."],
            default => [PricingRule::HeldForWeakerUnits, "Held: {$assessment->progress()}, the leader budget of {$assessment->leaderBudget} went to weaker units."],
        };

        $recommended = $night->status === NightStatus::Available
            ? $this->clamp($night->basePriceCents, $unit)
            : $night->basePriceCents;

        return new Recommendation(
            unitId: $unit->id,
            groupId: $assessment->groupId,
            date: $assessment->date,
            status: $night->status,
            basePriceCents: $night->basePriceCents,
            recommendedPriceCents: $recommended,
            assignedDiscount: 0.0,
            mode: $assessment->mode,
            rule: $rule,
            leaderRank: null,
            reason: $reason.$this->clampNote($night->basePriceCents, $recommended),
        );
    }

    private function priceLeader(GroupAssessment $assessment, Unit $unit, Night $night, int $rank, float $discount): Recommendation
    {
        // Rounded up to a whole currency unit, so rounding never gives more discount than was assigned.
        $discounted = min($night->basePriceCents, (int) ceil(round($night->basePriceCents * (1 - $discount), 6) / 100) * 100);
        $recommended = $this->clamp($discounted, $unit);

        $headline = match (true) {
            $recommended > $night->basePriceCents => 'Raised to the floor price',
            $recommended === $night->basePriceCents && $recommended > $discounted => 'Held at the floor price',
            $recommended === $night->basePriceCents => 'Unchanged after rounding',
            default => '-'.(int) round((1 - $recommended / $night->basePriceCents) * 100).'%',
        };

        $note = $recommended < $night->basePriceCents ? $this->clampNote($discounted, $recommended) : '';

        return new Recommendation(
            unitId: $unit->id,
            groupId: $assessment->groupId,
            date: $assessment->date,
            status: $night->status,
            basePriceCents: $night->basePriceCents,
            recommendedPriceCents: $recommended,
            assignedDiscount: $discount,
            mode: $assessment->mode,
            rule: PricingRule::PriceLeader,
            leaderRank: $rank + 1,
            reason: "{$headline}: {$assessment->progress()}, leader budget {$assessment->leaderBudget}, {$this->weakness($rank)} recent occupancy in the group.{$note}",
        );
    }

    private function clamp(int $priceCents, Unit $unit): int
    {
        return max($unit->floorPriceCents, min($unit->ceilingPriceCents, $priceCents));
    }

    private function clampNote(int $unclampedCents, int $recommendedCents): string
    {
        return match (true) {
            $recommendedCents > $unclampedCents => ' Limited to the floor price.',
            $recommendedCents < $unclampedCents => ' Limited to the ceiling price.',
            default => '',
        };
    }

    private function weakness(int $rank): string
    {
        if ($rank === 0) {
            return 'weakest';
        }

        $position = $rank + 1;
        $suffix = in_array($position % 100, [11, 12, 13], true)
            ? 'th'
            : match ($position % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };

        return "{$position}{$suffix} weakest";
    }

    /**
     * @param  list<Unit>  $units
     */
    private function countNights(array $units, DateTimeImmutable $date, NightStatus $status): int
    {
        return count(array_filter($units, fn (Unit $unit): bool => $unit->nightOn($date)?->status === $status));
    }

    /**
     * @param  list<Unit>  $units
     * @return list<DateTimeImmutable>
     */
    private function nightDates(array $units): array
    {
        $dates = [];

        foreach ($units as $unit) {
            foreach ($unit->nights as $night) {
                $dates[$night->date->format('Y-m-d')] = $night->date;
            }
        }

        ksort($dates);

        return array_values($dates);
    }
}
