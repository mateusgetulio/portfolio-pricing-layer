<?php

use App\Pricing\Data\GroupAssessment;
use App\Pricing\Data\Night;
use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\PricingConfig;
use App\Pricing\Data\Recommendation;
use App\Pricing\Data\Unit;
use App\Pricing\Enums\NightStatus;
use App\Pricing\FixturePortfolioSource;
use App\Pricing\PortfolioPricer;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\RandomPortfolioFactory;
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

const RANDOM_PORTFOLIOS = 2000;

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

function pricedRandomPortfolios(): Generator
{
    static $pricer = null;
    $pricer ??= pricer();

    foreach (randomPortfolioSeeds() as $seed) {
        try {
            $snapshot = (new RandomPortfolioFactory($seed))->make();
            $recommendations = $pricer->price($snapshot);
        } catch (Throwable $exception) {
            throw new RuntimeException("Random portfolio seed {$seed} could not be generated and priced. Rerun it with PRICING_TEST_SEED={$seed}. {$exception->getMessage()}", previous: $exception);
        }

        yield $seed => [$snapshot, $recommendations];
    }
}

function randomPortfolioSeeds(): array
{
    $seed = getenv('PRICING_TEST_SEED');

    if ($seed === false) {
        return range(1, RANDOM_PORTFOLIOS);
    }

    $parsed = filter_var($seed, FILTER_VALIDATE_INT);

    if ($parsed === false) {
        throw new InvalidArgumentException("PRICING_TEST_SEED must be an integer, got [{$seed}].");
    }

    return [$parsed];
}

function invariantFailure(string $invariant, int $seed): string
{
    return "{$invariant} failed for random portfolio seed {$seed}. Rerun it with PRICING_TEST_SEED={$seed} vendor/bin/pest --filter='{$invariant}\\b'";
}

function independentBookingStrengths(array $units, int $minHistoryNights): array
{
    $experienced = array_filter($units, fn (Unit $unit): bool => $unit->historyNights >= $minHistoryNights);
    $mean = $experienced === [] ? 0.0 : array_sum(array_map(fn (Unit $unit): float => $unit->trailingOccupancy, $experienced)) / count($experienced);

    $strengths = [];

    foreach ($units as $unit) {
        $strengths[$unit->id] = $mean > 0.0 && $unit->historyNights >= $minHistoryNights
            ? round($unit->trailingOccupancy / $mean, 9)
            : 1.0;
    }

    return $strengths;
}

function unitsById(PortfolioSnapshot $snapshot): array
{
    $units = [];

    foreach ($snapshot->units as $unit) {
        $units[$unit->id] = $unit;
    }

    return $units;
}

function describeNights(array $recommendations): array
{
    return array_values(array_map(
        fn (Recommendation $recommendation): string => "{$recommendation->unitId} on {$recommendation->date->format('Y-m-d')}",
        $recommendations,
    ));
}

function describeAssessments(array $assessments): array
{
    return array_values(array_map(
        fn (GroupAssessment $assessment): string => "{$assessment->groupId} on {$assessment->date->format('Y-m-d')}",
        $assessments,
    ));
}

function withNightBooked(PortfolioSnapshot $snapshot, string $unitId, DateTimeImmutable $date): PortfolioSnapshot
{
    $units = array_map(function (Unit $unit) use ($unitId, $date): Unit {
        if ($unit->id !== $unitId) {
            return $unit;
        }

        $nights = array_map(
            fn (Night $night): Night => $night->date->format('Y-m-d') === $date->format('Y-m-d')
                ? new Night($night->date, NightStatus::Booked, $night->basePriceCents)
                : $night,
            $unit->nights,
        );

        return new Unit($unit->id, $unit->groupId, $unit->name, $unit->floorPriceCents, $unit->ceilingPriceCents, $unit->trailingOccupancy, $unit->historyNights, $nights);
    }, $snapshot->units);

    return new PortfolioSnapshot($snapshot->asOf, $snapshot->groups, $units);
}

function groupNightSnapshot(PortfolioSnapshot $snapshot, string $groupId, DateTimeImmutable $date): PortfolioSnapshot
{
    $units = array_map(
        fn (Unit $unit): Unit => new Unit(
            $unit->id,
            $unit->groupId,
            $unit->name,
            $unit->floorPriceCents,
            $unit->ceilingPriceCents,
            $unit->trailingOccupancy,
            $unit->historyNights,
            array_values(array_filter($unit->nights, fn (Night $night): bool => $night->date->format('Y-m-d') === $date->format('Y-m-d'))),
        ),
        $snapshot->unitsInGroup($groupId),
    );

    return new PortfolioSnapshot(
        $snapshot->asOf,
        array_values(array_filter($snapshot->groups, fn ($group): bool => $group->id === $groupId)),
        $units,
    );
}

function shuffledSnapshot(PortfolioSnapshot $snapshot, int $seed): PortfolioSnapshot
{
    $random = new Randomizer(new Mt19937($seed));

    $units = array_map(
        fn (Unit $unit): Unit => new Unit($unit->id, $unit->groupId, $unit->name, $unit->floorPriceCents, $unit->ceilingPriceCents, $unit->trailingOccupancy, $unit->historyNights, $random->shuffleArray($unit->nights)),
        $snapshot->units,
    );

    return new PortfolioSnapshot($snapshot->asOf, $random->shuffleArray($snapshot->groups), $random->shuffleArray($units));
}
