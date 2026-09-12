<?php

use App\Pricing\Data\PricingConfig;
use App\Pricing\Enums\BudgetRounding;
use App\Pricing\Exceptions\InvalidPaceCurve;
use App\Pricing\Exceptions\InvalidPricingConfig;

it('accepts the shipped configuration', function () {
    $config = PricingConfig::fromArray(shippedPricingConfig());

    expect($config->minGroupSize)->toBe(3)
        ->and($config->budgetRounding)->toBe(BudgetRounding::Ceil)
        ->and($config->maxDiscount)->toBe(0.15)
        ->and($config->paceCurve->points)->toHaveCount(6);
});

it('rejects thresholds outside their range', function (string $key, mixed $value, string $message) {
    $config = array_replace(shippedPricingConfig(), [$key => $value]);

    expect(fn () => PricingConfig::fromArray($config))->toThrow(InvalidPricingConfig::class, $message);
})->with([
    'max discount above one' => ['max_discount', 1.5, 'max_discount must be above 0 and below 1.'],
    'negative protect margin' => ['protect_margin', -0.05, 'protect_margin must be at least 0 and below 1.'],
    'negative fill margin' => ['fill_margin', -0.05, 'fill_margin must be at least 0 and below 1.'],
    'negative leader taper' => ['leader_taper', -0.1, 'leader_taper must be at least 0 and below 1.'],
    'leader taper of one' => ['leader_taper', 1.0, 'leader_taper must be at least 0 and below 1.'],
    'group size of zero' => ['min_group_size', 0, 'min_group_size must be at least 1.'],
    'urgency window of zero days' => ['urgency_window_days', 0, 'urgency_window_days must be at least 1.'],
    'negative base step' => ['base_step', -0.01, 'base_step, shortfall_weight and urgency_weight cannot be negative.'],
    'negative history nights' => ['min_history_nights', -1, 'min_history_nights cannot be negative.'],
    'unknown budget rounding' => ['budget_rounding', 'floor', 'budget_rounding must be ceil or round.'],
    'discount given as text' => ['max_discount', 'high', 'max_discount must be a number.'],
    'missing threshold' => ['fill_margin', null, 'fill_margin must be a number.'],
]);

it('rejects a malformed pace curve in the configuration', function () {
    $config = array_replace(shippedPricingConfig(), ['pace_curve' => []]);

    expect(fn () => PricingConfig::fromArray($config))->toThrow(InvalidPaceCurve::class);
});
