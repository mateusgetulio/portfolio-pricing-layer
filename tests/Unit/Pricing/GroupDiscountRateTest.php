<?php

use App\Pricing\Data\PricingConfig;
use App\Pricing\GroupDiscountRate;

beforeEach(function () {
    $this->rate = new GroupDiscountRate(PricingConfig::fromArray(shippedPricingConfig()));
});

it('combines a base step, the shortfall and urgency into a whole percent', function (float $shortfall, int $leadTimeDays, float $rate) {
    expect($this->rate->forShortfall($shortfall, $leadTimeDays))->toBe($rate);
})->with([
    'worked example' => [0.8286 - 8 / 12, 8, 0.08],
    'no urgency beyond the urgency window' => [0.2, 30, 0.07],
    'full urgency on the night itself' => [0.12, 0, 0.10],
    'capped at the maximum discount' => [0.58, 8, 0.15],
]);
