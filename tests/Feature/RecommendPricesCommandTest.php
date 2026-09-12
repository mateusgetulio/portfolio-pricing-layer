<?php

use App\Pricing\FixturePortfolioSource;
use Illuminate\Support\Facades\Artisan;

it('prints the worked example for one night', function () {
    $this->artisan('pricing:recommend', ['--date' => '2026-09-19'])
        ->expectsOutputToContain('Downtown 1BR, Saturday 2026-09-19: 8 of 12 booked, target 10 by 8 days out. Behind pace, 2 price leaders at up to 8%.')
        ->expectsOutputToContain('-8%: 8 of 12 booked, target 10 by 8 days out, leader budget 2, weakest recent occupancy in the group.')
        ->expectsOutputToContain('-6%: 8 of 12 booked, target 10 by 8 days out, leader budget 2, 2nd weakest recent occupancy in the group.')
        ->assertSuccessful();
});

it('prints the recommended prices of the worked example', function () {
    Artisan::call('pricing:recommend', ['--date' => '2026-09-19']);

    expect(Artisan::output())
        ->toMatch('/unit-11\s+\|\s+available\s+\|\s+\$138\s+\|\s+\$130\s+\|\s+6%/')
        ->toMatch('/unit-12\s+\|\s+available\s+\|\s+\$139\s+\|\s+\$128\s+\|\s+8%/');
});

it('prints every night of the fixture when no date is given', function () {
    $this->artisan('pricing:recommend')
        ->expectsOutputToContain('Friday 2026-09-18')
        ->expectsOutputToContain('Saturday 2026-09-19')
        ->expectsOutputToContain('Sunday 2026-09-20')
        ->assertSuccessful();
});

it('explains an unknown fixture instead of failing with a stack trace', function () {
    $this->artisan('pricing:recommend', ['--fixture' => 'missing'])
        ->expectsOutputToContain('No fixture named [missing].')
        ->assertFailed();
});

it('explains a fixture that is not valid JSON', function () {
    $this->app->instance(FixturePortfolioSource::class, new FixturePortfolioSource(base_path('tests/fixtures')));

    $this->artisan('pricing:recommend', ['--fixture' => 'broken'])
        ->expectsOutputToContain('Fixture [broken] is not valid JSON.')
        ->assertFailed();
});

it('rejects a date that is not a calendar date', function () {
    $this->artisan('pricing:recommend', ['--date' => '2026-02-30'])
        ->expectsOutputToContain('The --date option must be a Y-m-d date.')
        ->assertFailed();
});

it('says when the fixture has no nights on the requested date', function () {
    $this->artisan('pricing:recommend', ['--date' => '2026-10-01'])
        ->expectsOutputToContain('No nights on 2026-10-01 in [twelve-apartments].')
        ->assertFailed();
});

it('explains a fixture that fails validation', function () {
    $this->app->instance(FixturePortfolioSource::class, new FixturePortfolioSource(base_path('tests/fixtures')));

    $this->artisan('pricing:recommend', ['--fixture' => 'floor-above-ceiling'])
        ->expectsOutputToContain('Unit [unit-01] has a floor price above its ceiling price.')
        ->assertFailed();
});
