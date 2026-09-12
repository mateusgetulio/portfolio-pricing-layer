<?php

use App\Pricing\Enums\DayType;
use App\Pricing\Exceptions\InvalidPaceCurve;
use App\Pricing\PaceCurve;

beforeEach(function () {
    $this->curve = PaceCurve::fromArray(shippedPricingConfig()['pace_curve']);
});

it('returns the shipped target at every point of the curve', function (int $leadTimeDays, DayType $dayType, float $target) {
    expect($this->curve->targetFor($leadTimeDays, $dayType))->toBe($target);
})->with([
    'weekday 1 day out' => [1, DayType::Weekday, 0.95],
    'weekend 1 day out' => [1, DayType::Weekend, 0.97],
    'weekday 3 days out' => [3, DayType::Weekday, 0.90],
    'weekend 3 days out' => [3, DayType::Weekend, 0.95],
    'weekday 7 days out' => [7, DayType::Weekday, 0.80],
    'weekend 7 days out' => [7, DayType::Weekend, 0.85],
    'weekday 14 days out' => [14, DayType::Weekday, 0.65],
    'weekend 14 days out' => [14, DayType::Weekend, 0.70],
    'weekday 30 days out' => [30, DayType::Weekday, 0.45],
    'weekend 30 days out' => [30, DayType::Weekend, 0.50],
    'weekday 60 days out' => [60, DayType::Weekday, 0.30],
    'weekend 60 days out' => [60, DayType::Weekend, 0.35],
]);

it('interpolates linearly between points', function (int $leadTimeDays, DayType $dayType, float $target) {
    expect($this->curve->targetFor($leadTimeDays, $dayType))->toEqualWithDelta($target, 0.0001);
})->with([
    'weekend eight days out' => [8, DayType::Weekend, 0.8286],
    'weekday forty five days out' => [45, DayType::Weekday, 0.375],
]);

it('holds the first target for nights closer than the first point', function () {
    expect($this->curve->targetFor(0, DayType::Weekend))->toBe(0.97);
});

it('holds the last target beyond the longest lead time', function () {
    expect($this->curve->targetFor(120, DayType::Weekday))->toBe(0.30);
});

it('rejects a malformed pace curve', function (array $points, string $message) {
    expect(fn () => PaceCurve::fromArray($points))->toThrow(InvalidPaceCurve::class, $message);
})->with([
    'fewer than two points' => [
        [['lead_time_days' => 1, 'weekday' => 0.9, 'weekend' => 0.9]],
        'A pace curve needs at least two points.',
    ],
    'lead times not strictly increasing' => [
        [['lead_time_days' => 7, 'weekday' => 0.8, 'weekend' => 0.8], ['lead_time_days' => 7, 'weekday' => 0.7, 'weekend' => 0.7]],
        'Pace curve lead times must be strictly increasing.',
    ],
    'target above one' => [
        [['lead_time_days' => 1, 'weekday' => 1.2, 'weekend' => 0.9], ['lead_time_days' => 7, 'weekday' => 0.7, 'weekend' => 0.7]],
        'The weekday target at 1 days must be greater than 0 and at most 1, got 1.2.',
    ],
    'target of zero' => [
        [['lead_time_days' => 1, 'weekday' => 0.9, 'weekend' => 0], ['lead_time_days' => 7, 'weekday' => 0.7, 'weekend' => 0.7]],
        'The weekend target at 1 days must be greater than 0 and at most 1, got 0.',
    ],
    'target increasing with lead time' => [
        [['lead_time_days' => 1, 'weekday' => 0.6, 'weekend' => 0.9], ['lead_time_days' => 7, 'weekday' => 0.7, 'weekend' => 0.7]],
        'The weekday target cannot increase as lead time grows (1 to 7 days).',
    ],
    'negative lead time' => [
        [['lead_time_days' => -1, 'weekday' => 0.9, 'weekend' => 0.9], ['lead_time_days' => 7, 'weekday' => 0.7, 'weekend' => 0.7]],
        'Pace curve lead times cannot be negative, got -1.',
    ],
    'missing weekend target' => [
        [['lead_time_days' => 1, 'weekday' => 0.9], ['lead_time_days' => 7, 'weekday' => 0.7, 'weekend' => 0.7]],
        'Each pace curve point needs an integer lead_time_days and numeric weekday and weekend targets.',
    ],
    'target given as text' => [
        [['lead_time_days' => 1, 'weekday' => '0.9', 'weekend' => 0.9], ['lead_time_days' => 7, 'weekday' => 0.7, 'weekend' => 0.7]],
        'Each pace curve point needs an integer lead_time_days and numeric weekday and weekend targets.',
    ],
    'points not given as a list' => [
        ['near' => ['lead_time_days' => 1, 'weekday' => 0.9, 'weekend' => 0.9], 'far' => ['lead_time_days' => 7, 'weekday' => 0.7, 'weekend' => 0.7]],
        'Pace curve points must be a list.',
    ],
]);
