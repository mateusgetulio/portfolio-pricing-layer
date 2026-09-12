<?php

namespace App\Pricing;

use App\Pricing\Data\PricingConfig;

final readonly class GroupDiscountRate
{
    public function __construct(private PricingConfig $config) {}

    public function forShortfall(float $shortfall, int $leadTimeDays): float
    {
        $window = $this->config->urgencyWindowDays;
        $urgency = min(1.0, max(0.0, ($window - $leadTimeDays) / $window));

        $rate = $this->config->baseStep
            + $this->config->shortfallWeight * $shortfall
            + $this->config->urgencyWeight * $urgency;

        return round(min($this->config->maxDiscount, $rate), 2);
    }
}
