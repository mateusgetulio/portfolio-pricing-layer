<?php

use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\PricingConfig;
use App\Pricing\Data\Unit;
use App\Pricing\FixturePortfolioSource;
use App\Pricing\PortfolioPricer;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function snapshotData(): array
{
    return [
        'as_of' => '2026-09-11',
        'groups' => [['id' => 'downtown-1br', 'name' => 'Downtown 1BR']],
        'units' => [sampleUnit()],
    ];
}

function sampleUnit(array $overrides = []): array
{
    return array_replace([
        'id' => 'unit-01',
        'group_id' => 'downtown-1br',
        'name' => 'Apartment 1',
        'floor_price_cents' => 9500,
        'ceiling_price_cents' => 26000,
        'trailing_occupancy' => 0.8,
        'history_nights' => 90,
        'nights' => [sampleNight()],
    ], $overrides);
}

function sampleNight(): array
{
    return ['date' => '2026-09-19', 'status' => 'available', 'base_price_cents' => 14200];
}

function makeUnit(string $id, float $trailingOccupancy, int $historyNights = 90): Unit
{
    return new Unit($id, 'downtown-1br', $id, 9500, 26000, $trailingOccupancy, $historyNights, []);
}

function shippedPricingConfig(): array
{
    return require __DIR__.'/../config/portfolio_pricing.php';
}

function pricer(array $overrides = []): PortfolioPricer
{
    return new PortfolioPricer(PricingConfig::fromArray(array_replace(shippedPricingConfig(), $overrides)));
}

function portfolioFixture(string $name = 'twelve-apartments'): PortfolioSnapshot
{
    return (new FixturePortfolioSource(__DIR__.'/../fixtures'))->load($name);
}

function scenario(array $units, string $night = '2026-09-19', int $leadTimeDays = 8): PortfolioSnapshot
{
    $asOf = (new DateTimeImmutable($night))->modify("-{$leadTimeDays} days")->format('Y-m-d');

    return PortfolioSnapshot::fromArray([
        'as_of' => $asOf,
        'groups' => [['id' => 'downtown-1br', 'name' => 'Downtown 1BR']],
        'units' => array_map(fn (array $unit): array => sampleUnit([
            'id' => $unit['id'],
            'trailing_occupancy' => $unit['occupancy'] ?? 0.72,
            'history_nights' => $unit['history'] ?? 90,
            'floor_price_cents' => $unit['floor'] ?? 9500,
            'ceiling_price_cents' => $unit['ceiling'] ?? 26000,
            'nights' => [['date' => $night, 'status' => $unit['status'], 'base_price_cents' => $unit['base'] ?? 14000]],
        ]), $units),
    ]);
}

function twelveApartments(int $booked): PortfolioSnapshot
{
    $data = json_decode((string) file_get_contents(__DIR__.'/../fixtures/twelve-apartments.json'), true);

    foreach ($data['units'] as $index => $unit) {
        $saturday = array_values(array_filter($unit['nights'], fn (array $night): bool => $night['date'] === '2026-09-19'))[0];
        $data['units'][$index]['nights'] = [array_replace($saturday, ['status' => $index < $booked ? 'booked' : 'available'])];
    }

    return PortfolioSnapshot::fromArray($data);
}
