<?php

use App\Pricing\BookingStrength;
use App\Pricing\Data\Unit;
use App\Pricing\Enums\NightStatus;
use App\Pricing\Exceptions\FixtureNotFound;
use App\Pricing\Exceptions\InvalidPortfolioSnapshot;
use App\Pricing\FixturePortfolioSource;

beforeEach(function () {
    $this->source = new FixturePortfolioSource(__DIR__.'/../../../fixtures');
});

it('loads the twelve apartment fixture', function () {
    $snapshot = $this->source->load('twelve-apartments');

    $bookedOnSaturday = array_filter(
        $snapshot->units,
        fn (Unit $unit) => $unit->nightOn(new DateTimeImmutable('2026-09-19'))?->status === NightStatus::Booked,
    );

    expect($snapshot->units)->toHaveCount(12)
        ->and($snapshot->groups)->toHaveCount(1)
        ->and($bookedOnSaturday)->toHaveCount(8);
});

it('reproduces the booking strengths of the worked example', function () {
    $snapshot = $this->source->load('twelve-apartments');

    $strengths = (new BookingStrength(30))->forUnits($snapshot->units);

    expect(round($strengths['unit-09'], 2))->toBe(1.18)
        ->and(round($strengths['unit-10'], 2))->toBe(1.06)
        ->and(round($strengths['unit-11'], 2))->toBe(0.83)
        ->and(round($strengths['unit-12'], 2))->toBe(0.71);
});

it('rejects fixture names that do not exist', function (string $name) {
    expect(fn () => $this->source->load($name))->toThrow(FixtureNotFound::class, "No fixture named [{$name}].");
})->with([
    'unknown name' => ['missing'],
    'path outside the fixture directory' => ['../composer'],
]);

it('rejects a fixture that is not valid JSON', function () {
    $source = new FixturePortfolioSource(__DIR__.'/../../fixtures');

    expect(fn () => $source->load('broken'))->toThrow(InvalidPortfolioSnapshot::class, 'Fixture [broken] is not valid JSON.');
});
