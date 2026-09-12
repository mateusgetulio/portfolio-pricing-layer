<?php

namespace App\Pricing\Data;

use App\Pricing\Enums\BudgetRounding;
use App\Pricing\Exceptions\InvalidPricingConfig;
use App\Pricing\PaceCurve;

final readonly class PricingConfig
{
    public function __construct(
        public PaceCurve $paceCurve,
        public int $minGroupSize,
        public float $protectMargin,
        public float $fillMargin,
        public BudgetRounding $budgetRounding,
        public int $urgencyWindowDays,
        public float $baseStep,
        public float $shortfallWeight,
        public float $urgencyWeight,
        public float $maxDiscount,
        public float $leaderTaper,
        public int $minHistoryNights,
    ) {
        self::ensure($minGroupSize >= 1, 'min_group_size must be at least 1.');
        self::ensure($protectMargin >= 0.0 && $protectMargin < 1.0, 'protect_margin must be at least 0 and below 1.');
        self::ensure($fillMargin >= 0.0 && $fillMargin < 1.0, 'fill_margin must be at least 0 and below 1.');
        self::ensure($urgencyWindowDays >= 1, 'urgency_window_days must be at least 1.');
        self::ensure($baseStep >= 0.0 && $shortfallWeight >= 0.0 && $urgencyWeight >= 0.0, 'base_step, shortfall_weight and urgency_weight cannot be negative.');
        self::ensure($maxDiscount > 0.0 && $maxDiscount < 1.0, 'max_discount must be above 0 and below 1.');
        self::ensure($leaderTaper >= 0.0 && $leaderTaper < 1.0, 'leader_taper must be at least 0 and below 1.');
        self::ensure($minHistoryNights >= 0, 'min_history_nights cannot be negative.');
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $paceCurve = $config['pace_curve'] ?? null;

        if (! is_array($paceCurve)) {
            throw new InvalidPricingConfig('pace_curve must be a list of points.');
        }

        return new self(
            paceCurve: PaceCurve::fromArray($paceCurve),
            minGroupSize: self::intFrom($config, 'min_group_size'),
            protectMargin: self::floatFrom($config, 'protect_margin'),
            fillMargin: self::floatFrom($config, 'fill_margin'),
            budgetRounding: BudgetRounding::tryFrom(self::stringFrom($config, 'budget_rounding'))
                ?? throw new InvalidPricingConfig('budget_rounding must be ceil or round.'),
            urgencyWindowDays: self::intFrom($config, 'urgency_window_days'),
            baseStep: self::floatFrom($config, 'base_step'),
            shortfallWeight: self::floatFrom($config, 'shortfall_weight'),
            urgencyWeight: self::floatFrom($config, 'urgency_weight'),
            maxDiscount: self::floatFrom($config, 'max_discount'),
            leaderTaper: self::floatFrom($config, 'leader_taper'),
            minHistoryNights: self::intFrom($config, 'min_history_nights'),
        );
    }

    private static function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidPricingConfig($message);
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    private static function intFrom(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidPricingConfig("{$key} must be an integer.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    private static function floatFrom(array $config, string $key): float
    {
        $value = $config[$key] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidPricingConfig("{$key} must be a number.");
        }

        return (float) $value;
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    private static function stringFrom(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidPricingConfig("{$key} must be a string.");
        }

        return $value;
    }
}
