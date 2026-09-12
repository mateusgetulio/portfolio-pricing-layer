<?php

use App\Pricing\Data\PricingConfig;
use App\Pricing\Data\Recommendation;
use App\Pricing\Data\RecommendationSet;
use App\Pricing\Enums\NightStatus;
use App\Pricing\Enums\PricingMode;
use App\Pricing\Enums\PricingRule;
use Tests\Support\RandomPortfolioFactory;

it('INV-1 never changes a booked or blocked night', function () {
    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        foreach ($recommendations->recommendations as $recommendation) {
            if ($recommendation->status !== NightStatus::Available) {
                expect($recommendation->recommendedPriceCents)->toBe($recommendation->basePriceCents, invariantFailure('INV-1', $seed));
            }
        }
    }
});

it('INV-2 keeps every available night within its floor and ceiling', function () {
    foreach (pricedRandomPortfolios() as $seed => [$snapshot, $recommendations]) {
        $units = [];

        foreach ($snapshot->units as $unit) {
            $units[$unit->id] = $unit;
        }

        foreach ($recommendations->recommendations as $recommendation) {
            if ($recommendation->status === NightStatus::Available) {
                expect($recommendation->recommendedPriceCents)
                    ->toBeGreaterThanOrEqual($units[$recommendation->unitId]->floorPriceCents, invariantFailure('INV-2', $seed))
                    ->toBeLessThanOrEqual($units[$recommendation->unitId]->ceilingPriceCents, invariantFailure('INV-2', $seed));
            }
        }
    }
});

it('INV-3 gives no discount outside fill mode', function () {
    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        foreach ($recommendations->recommendations as $recommendation) {
            if ($recommendation->mode === PricingMode::Fill) {
                continue;
            }

            expect($recommendation->assignedDiscount)->toBe(0.0, invariantFailure('INV-3', $seed))
                ->and($recommendation->isPriceLeader())->toBeFalse(invariantFailure('INV-3', $seed))
                ->and($recommendation->recommendedPriceCents)->toBe($recommendation->basePriceCents, invariantFailure('INV-3', $seed));
        }
    }
});

it('INV-4 never picks more price leaders than the bookings still needed', function () {
    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        foreach ($recommendations->assessments as $assessment) {
            $leaders = count(array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->isPriceLeader(),
            ));

            $bookingsStillNeeded = max(0, min(
                (int) ceil(round($assessment->target * $assessment->sellableUnits(), 9)) - $assessment->bookedUnits,
                $assessment->availableUnits,
            ));

            expect($leaders)->toBeLessThanOrEqual($assessment->mode === PricingMode::Fill ? $bookingsStillNeeded : 0, invariantFailure('INV-4', $seed));
        }
    }
});

it('INV-5 never gives a stronger unit a larger discount than a weaker unit', function () {
    $minHistoryNights = PricingConfig::fromArray(shippedPricingConfig())->minHistoryNights;

    foreach (pricedRandomPortfolios() as $seed => [$snapshot, $recommendations]) {
        $units = [];

        foreach ($snapshot->units as $unit) {
            $units[$unit->id] = $unit;
        }

        foreach ($recommendations->assessments as $assessment) {
            $comparable = array_values(array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->status === NightStatus::Available
                    && $units[$recommendation->unitId]->historyNights >= $minHistoryNights,
            ));

            foreach ($comparable as $stronger) {
                foreach ($comparable as $weaker) {
                    if ($units[$stronger->unitId]->trailingOccupancy > $units[$weaker->unitId]->trailingOccupancy) {
                        expect($stronger->assignedDiscount)->toBeLessThanOrEqual($weaker->assignedDiscount, invariantFailure('INV-5', $seed));
                    }
                }
            }
        }
    }
});

it('INV-6 never raises a price and never discounts beyond the maximum', function () {
    $maxDiscount = PricingConfig::fromArray(shippedPricingConfig())->maxDiscount;

    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        foreach ($recommendations->recommendations as $recommendation) {
            if ($recommendation->status !== NightStatus::Available) {
                continue;
            }

            expect($recommendation->recommendedPriceCents)->toBeLessThanOrEqual($recommendation->basePriceCents, invariantFailure('INV-6', $seed))
                ->and($recommendation->assignedDiscount)->toBeLessThanOrEqual($maxDiscount, invariantFailure('INV-6', $seed))
                ->and($recommendation->discount())->toBeLessThanOrEqual($recommendation->assignedDiscount + 0.000001, invariantFailure('INV-6', $seed));
        }
    }
});

it('INV-7 passes groups smaller than the minimum size through unchanged', function () {
    $minGroupSize = PricingConfig::fromArray(shippedPricingConfig())->minGroupSize;

    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        foreach ($recommendations->assessments as $assessment) {
            if ($assessment->groupSize >= $minGroupSize) {
                expect($assessment->mode)->not->toBe(PricingMode::PassThrough, invariantFailure('INV-7', $seed));

                continue;
            }

            expect($assessment->mode)->toBe(PricingMode::PassThrough, invariantFailure('INV-7', $seed));

            foreach ($recommendations->forGroupOn($assessment->groupId, $assessment->date) as $recommendation) {
                expect($recommendation->recommendedPriceCents)->toBe($recommendation->basePriceCents, invariantFailure('INV-7', $seed));
            }
        }
    }
});

it('INV-8 is deterministic, pure and independent of input order', function () {
    $pricer = pricer();

    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        $snapshot = (new RandomPortfolioFactory($seed))->make();
        $before = serialize($snapshot);

        $priced = $pricer->price($snapshot);

        expect(serialize($snapshot))->toBe($before, invariantFailure('INV-8', $seed))
            ->and($priced)->toEqual($recommendations, invariantFailure('INV-8', $seed))
            ->and($pricer->price(shuffledSnapshot($snapshot, $seed)))->toEqual($recommendations, invariantFailure('INV-8', $seed));
    }
});

it('INV-9 explains every night with the rule that fits its status and mode', function () {
    $phrases = [
        PricingRule::BookedNight->value => ['Booked night'],
        PricingRule::BlockedNight->value => ['Blocked night'],
        PricingRule::SmallGroup->value => ['too small for portfolio pricing'],
        PricingRule::AheadOfPace->value => ['ahead of pace'],
        PricingRule::OnPace->value => ['on pace'],
        PricingRule::HeldForWeakerUnits->value => ['went to weaker units', 'would round to 0%'],
        PricingRule::PriceLeader->value => ['recent occupancy in the group'],
    ];

    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        foreach ($recommendations->recommendations as $recommendation) {
            $fittingRules = match (true) {
                $recommendation->status === NightStatus::Booked => [PricingRule::BookedNight],
                $recommendation->status === NightStatus::Blocked => [PricingRule::BlockedNight],
                $recommendation->mode === PricingMode::PassThrough => [PricingRule::SmallGroup],
                $recommendation->mode === PricingMode::Protect => [PricingRule::AheadOfPace],
                $recommendation->mode === PricingMode::Hold => [PricingRule::OnPace],
                default => [PricingRule::PriceLeader, PricingRule::HeldForWeakerUnits],
            };

            $matchingPhrases = array_filter(
                $phrases[$recommendation->rule->value],
                fn (string $phrase) => str_contains($recommendation->reason, $phrase),
            );

            expect(in_array($recommendation->rule, $fittingRules, true))->toBeTrue(invariantFailure('INV-9', $seed))
                ->and($matchingPhrases)->not->toBeEmpty(invariantFailure('INV-9', $seed));
        }
    }
});

it('INV-10 never assigns more discount when demand rises', function () {
    $pricer = pricer();

    $modeOrder = [
        PricingMode::Fill->value => 0,
        PricingMode::Hold->value => 1,
        PricingMode::Protect->value => 2,
        PricingMode::PassThrough->value => 3,
    ];

    foreach (pricedRandomPortfolios() as $seed => [$snapshot, $recommendations]) {
        foreach ($recommendations->assessments as $assessment) {
            $totalDiscount = fn (RecommendationSet $set) => array_sum(array_map(
                fn (Recommendation $recommendation) => $recommendation->assignedDiscount,
                $set->forGroupOn($assessment->groupId, $assessment->date),
            ));

            $available = array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->status === NightStatus::Available,
            );

            $groupNight = groupNightSnapshot($snapshot, $assessment->groupId, $assessment->date);

            foreach ($available as $unitToBook) {
                $moreDemand = $pricer->price(withNightBooked($groupNight, $unitToBook->unitId, $assessment->date));
                $after = $moreDemand->assessmentFor($assessment->groupId, $assessment->date);

                expect($after->leaderBudget)->toBeLessThanOrEqual($assessment->leaderBudget, invariantFailure('INV-10', $seed))
                    ->and($after->discountRate)->toBeLessThanOrEqual($assessment->discountRate, invariantFailure('INV-10', $seed))
                    ->and($totalDiscount($moreDemand))->toBeLessThanOrEqual($totalDiscount($recommendations) + 0.000001, invariantFailure('INV-10', $seed))
                    ->and($modeOrder[$after->mode->value])->toBeGreaterThanOrEqual($modeOrder[$assessment->mode->value], invariantFailure('INV-10', $seed));
            }
        }
    }
});
