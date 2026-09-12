<?php

use App\Pricing\Data\GroupAssessment;
use App\Pricing\Data\PricingConfig;
use App\Pricing\Data\Recommendation;
use App\Pricing\Data\RecommendationSet;
use App\Pricing\Enums\NightStatus;
use App\Pricing\Enums\PricingMode;
use App\Pricing\Enums\PricingRule;
use Tests\Support\RandomPortfolioFactory;

it('INV-1 never changes a booked or blocked night', function () {
    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        $changed = array_filter(
            $recommendations->recommendations,
            fn (Recommendation $recommendation) => $recommendation->status !== NightStatus::Available
                && $recommendation->recommendedPriceCents !== $recommendation->basePriceCents,
        );

        expect(describeNights($changed))->toBe([], invariantFailure('INV-1', $seed));
    }
});

it('INV-2 keeps every available night within its floor and ceiling', function () {
    foreach (pricedRandomPortfolios() as $seed => [$snapshot, $recommendations]) {
        $units = unitsById($snapshot);

        $outsideLimits = array_filter(
            $recommendations->recommendations,
            fn (Recommendation $recommendation) => $recommendation->status === NightStatus::Available
                && ($recommendation->recommendedPriceCents < $units[$recommendation->unitId]->floorPriceCents
                    || $recommendation->recommendedPriceCents > $units[$recommendation->unitId]->ceilingPriceCents),
        );

        expect(describeNights($outsideLimits))->toBe([], invariantFailure('INV-2', $seed));
    }
});

it('INV-3 gives no discount outside fill mode', function () {
    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        $discounted = array_filter(
            $recommendations->recommendations,
            fn (Recommendation $recommendation) => $recommendation->mode !== PricingMode::Fill
                && ($recommendation->assignedDiscount !== 0.0
                    || $recommendation->isPriceLeader()
                    || $recommendation->recommendedPriceCents !== $recommendation->basePriceCents),
        );

        expect(describeNights($discounted))->toBe([], invariantFailure('INV-3', $seed));
    }
});

it('INV-4 never picks more price leaders than the bookings still needed', function () {
    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        $overBudget = array_filter($recommendations->assessments, function (GroupAssessment $assessment) use ($recommendations): bool {
            $leaders = count(array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->isPriceLeader(),
            ));

            $bookingsStillNeeded = max(0, min(
                (int) ceil(round($assessment->target * $assessment->sellableUnits(), 9)) - $assessment->bookedUnits,
                $assessment->availableUnits,
            ));

            return $leaders > ($assessment->mode === PricingMode::Fill ? $bookingsStillNeeded : 0);
        });

        expect(describeAssessments($overBudget))->toBe([], invariantFailure('INV-4', $seed));
    }
});

it('INV-5 never gives a stronger unit a larger discount than a weaker unit', function () {
    $minHistoryNights = PricingConfig::fromArray(shippedPricingConfig())->minHistoryNights;

    foreach (pricedRandomPortfolios() as $seed => [$snapshot, $recommendations]) {
        $violations = [];

        foreach ($recommendations->assessments as $assessment) {
            $strengths = independentBookingStrengths($snapshot->unitsInGroup($assessment->groupId), $minHistoryNights);
            $available = array_values(array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->status === NightStatus::Available,
            ));

            foreach ($available as $stronger) {
                foreach ($available as $weaker) {
                    if ($strengths[$stronger->unitId] > $strengths[$weaker->unitId]
                        && $stronger->assignedDiscount > $weaker->assignedDiscount) {
                        $violations[] = "{$stronger->unitId} discounted more than weaker {$weaker->unitId} on {$assessment->date->format('Y-m-d')}";
                    }
                }
            }
        }

        expect($violations)->toBe([], invariantFailure('INV-5', $seed));
    }
});

it('INV-6 never raises a price and never discounts beyond the maximum', function () {
    $maxDiscount = PricingConfig::fromArray(shippedPricingConfig())->maxDiscount;

    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        $violations = array_filter(
            $recommendations->recommendations,
            fn (Recommendation $recommendation) => $recommendation->status === NightStatus::Available
                && ($recommendation->recommendedPriceCents > $recommendation->basePriceCents
                    || $recommendation->assignedDiscount > $maxDiscount
                    || $recommendation->discount() > $recommendation->assignedDiscount + 0.000001),
        );

        expect(describeNights($violations))->toBe([], invariantFailure('INV-6', $seed));
    }
});

it('INV-7 passes groups smaller than the minimum size through unchanged', function () {
    $minGroupSize = PricingConfig::fromArray(shippedPricingConfig())->minGroupSize;

    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        $violations = array_filter($recommendations->assessments, function (GroupAssessment $assessment) use ($recommendations, $minGroupSize): bool {
            if ($assessment->groupSize >= $minGroupSize) {
                return $assessment->mode === PricingMode::PassThrough;
            }

            $changed = array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->recommendedPriceCents !== $recommendation->basePriceCents,
            );

            return $assessment->mode !== PricingMode::PassThrough || $changed !== [];
        });

        expect(describeAssessments($violations))->toBe([], invariantFailure('INV-7', $seed));
    }
});

it('INV-8 is deterministic, pure and independent of input order', function () {
    $pricer = pricer();

    foreach (pricedRandomPortfolios() as $seed => [, $recommendations]) {
        $snapshot = (new RandomPortfolioFactory($seed))->make();
        $before = serialize($snapshot);
        $expected = serialize($recommendations);

        expect(serialize($pricer->price($snapshot)))->toBe($expected, invariantFailure('INV-8', $seed))
            ->and(serialize($pricer->price(shuffledSnapshot($snapshot, $seed))))->toBe($expected, invariantFailure('INV-8', $seed))
            ->and(serialize($snapshot))->toBe($before, invariantFailure('INV-8', $seed));
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
        $unexplained = array_filter($recommendations->recommendations, function (Recommendation $recommendation) use ($phrases): bool {
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

            return ! in_array($recommendation->rule, $fittingRules, true) || $matchingPhrases === [];
        });

        expect(describeNights($unexplained))->toBe([], invariantFailure('INV-9', $seed));
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
        $violations = [];

        foreach ($recommendations->assessments as $assessment) {
            $totalDiscount = fn (RecommendationSet $set) => array_sum(array_map(
                fn (Recommendation $recommendation) => $recommendation->assignedDiscount,
                $set->forGroupOn($assessment->groupId, $assessment->date),
            ));
            $discountBefore = $totalDiscount($recommendations);
            $groupNight = groupNightSnapshot($snapshot, $assessment->groupId, $assessment->date);

            $available = array_filter(
                $recommendations->forGroupOn($assessment->groupId, $assessment->date),
                fn (Recommendation $recommendation) => $recommendation->status === NightStatus::Available,
            );

            foreach ($available as $unitToBook) {
                $moreDemand = $pricer->price(withNightBooked($groupNight, $unitToBook->unitId, $assessment->date));
                $after = $moreDemand->assessmentFor($assessment->groupId, $assessment->date);

                if ($after->leaderBudget > $assessment->leaderBudget
                    || $after->discountRate > $assessment->discountRate
                    || $totalDiscount($moreDemand) > $discountBefore + 0.000001
                    || $modeOrder[$after->mode->value] < $modeOrder[$assessment->mode->value]) {
                    $violations[] = "booking {$unitToBook->unitId} in {$assessment->groupId} on {$assessment->date->format('Y-m-d')}";
                }
            }
        }

        expect($violations)->toBe([], invariantFailure('INV-10', $seed));
    }
});
