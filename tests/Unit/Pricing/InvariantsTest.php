<?php

use App\Pricing\BookingStrength;
use App\Pricing\Data\PricingConfig;
use App\Pricing\Data\Recommendation;
use App\Pricing\Data\RecommendationSet;
use App\Pricing\Enums\NightStatus;
use App\Pricing\Enums\PricingMode;
use App\Pricing\Enums\PricingRule;

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

it('INV-4 never picks more price leaders than the leader budget', function () {
    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        foreach ($recommendations->assessments as $assessment) {
            $leaders = array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->isPriceLeader(),
            );

            expect(count($leaders))->toBeLessThanOrEqual($assessment->leaderBudget, invariantFailure('INV-4', $seed));
        }
    }
});

it('INV-5 never gives a stronger unit a larger discount than a weaker unit', function () {
    $bookingStrength = new BookingStrength(PricingConfig::fromArray(shippedPricingConfig())->minHistoryNights);

    foreach (pricedRandomPortfolios() as $seed => [$snapshot, $recommendations]) {
        foreach ($recommendations->assessments as $assessment) {
            $strengths = $bookingStrength->forUnits($snapshot->unitsInGroup($assessment->groupId));
            $available = array_values(array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->status === NightStatus::Available,
            ));

            foreach ($available as $stronger) {
                foreach ($available as $weaker) {
                    if ($strengths[$stronger->unitId] > $strengths[$weaker->unitId]) {
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
    foreach (pricedRandomPortfolios() as $seed => [$snapshot, $recommendations]) {
        $before = serialize($snapshot);

        $again = pricer()->price($snapshot);
        $shuffled = pricer()->price(shuffledSnapshot($snapshot, $seed));

        expect($again)->toEqual($recommendations, invariantFailure('INV-8', $seed))
            ->and($shuffled)->toEqual($recommendations, invariantFailure('INV-8', $seed))
            ->and(serialize($snapshot))->toBe($before, invariantFailure('INV-8', $seed));
    }
});

it('INV-9 explains every night with the rule that decided it', function () {
    $phrases = [
        PricingRule::BookedNight->value => 'Booked night',
        PricingRule::BlockedNight->value => 'Blocked night',
        PricingRule::SmallGroup->value => 'too small for portfolio pricing',
        PricingRule::AheadOfPace->value => 'ahead of pace',
        PricingRule::OnPace->value => 'on pace',
        PricingRule::HeldForWeakerUnits->value => 'went to weaker units',
        PricingRule::PriceLeader->value => 'recent occupancy in the group',
    ];

    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        foreach ($recommendations->recommendations as $recommendation) {
            expect(str_contains($recommendation->reason, $phrases[$recommendation->rule->value]))->toBeTrue(invariantFailure('INV-9', $seed));
        }
    }
});

it('INV-10 never discounts more when demand rises', function () {
    $modeOrder = [
        PricingMode::Fill->value => 0,
        PricingMode::Hold->value => 1,
        PricingMode::Protect->value => 2,
        PricingMode::PassThrough->value => 3,
    ];

    foreach (pricedRandomPortfolios() as $seed => [$snapshot, $recommendations]) {
        foreach ($recommendations->assessments as $assessment) {
            $available = array_values(array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->status === NightStatus::Available,
            ));

            if ($available === []) {
                continue;
            }

            $moreDemand = pricer()->price(withNightBooked($snapshot, $available[0]->unitId, $assessment->date));
            $after = $moreDemand->assessmentFor($assessment->groupId, $assessment->date);
            $totalDiscount = fn (RecommendationSet $set) => array_sum(array_map(
                fn (Recommendation $recommendation) => $recommendation->assignedDiscount,
                $set->forGroupOn($assessment->groupId, $assessment->date),
            ));

            expect($after->leaderBudget)->toBeLessThanOrEqual($assessment->leaderBudget, invariantFailure('INV-10', $seed))
                ->and($after->discountRate)->toBeLessThanOrEqual($assessment->discountRate, invariantFailure('INV-10', $seed))
                ->and($totalDiscount($moreDemand))->toBeLessThanOrEqual($totalDiscount($recommendations) + 0.000001, invariantFailure('INV-10', $seed))
                ->and($modeOrder[$after->mode->value])->toBeGreaterThanOrEqual($modeOrder[$assessment->mode->value], invariantFailure('INV-10', $seed));
        }
    }
});
