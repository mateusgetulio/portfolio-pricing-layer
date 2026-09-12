<?php

namespace App\Console\Commands;

use App\Pricing\Data\GroupAssessment;
use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\Recommendation;
use App\Pricing\Data\RecommendationSet;
use App\Pricing\Enums\PricingMode;
use App\Pricing\Exceptions\FixtureNotFound;
use App\Pricing\Exceptions\InvalidPortfolioSnapshot;
use App\Pricing\FixturePortfolioSource;
use App\Pricing\PortfolioPricer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pricing:recommend
    {--fixture=twelve-apartments : Fixture name in the fixtures directory, without .json}
    {--date= : Only show one night, as Y-m-d}')]
#[Description('Show portfolio pricing recommendations for a fixture')]
class RecommendPrices extends Command
{
    public function handle(FixturePortfolioSource $fixtures, PortfolioPricer $pricer): int
    {
        $fixture = $this->option('fixture');
        $date = $this->option('date');

        if (! is_string($fixture)) {
            $this->error('The --fixture option needs a fixture name.');

            return self::FAILURE;
        }

        if (is_string($date) && ! $this->isCalendarDate($date)) {
            $this->error('The --date option must be a Y-m-d date.');

            return self::FAILURE;
        }

        try {
            $snapshot = $fixtures->load($fixture);
        } catch (FixtureNotFound|InvalidPortfolioSnapshot $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $recommendations = $pricer->price($snapshot);
        $assessments = $this->assessmentsOn($recommendations, is_string($date) ? $date : null);

        if ($assessments === []) {
            $this->error(is_string($date) ? "No nights on {$date} in [{$fixture}]." : "Fixture [{$fixture}] has no nights.");

            return self::FAILURE;
        }

        foreach ($assessments as $assessment) {
            $this->newLine();
            $this->line($this->headline($snapshot, $recommendations, $assessment));
            $this->table(
                ['Unit', 'Status', 'Base', 'Recommended', 'Discount', 'Reason'],
                array_map(fn (Recommendation $recommendation): array => [
                    $recommendation->unitId,
                    $recommendation->status->value,
                    $this->money($recommendation->basePriceCents),
                    $this->money($recommendation->recommendedPriceCents),
                    $this->percent($recommendation->discount()),
                    $recommendation->reason,
                ], $recommendations->forGroupOn($assessment->groupId, $assessment->date)),
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return list<GroupAssessment>
     */
    private function assessmentsOn(RecommendationSet $recommendations, ?string $date): array
    {
        return array_values(array_filter(
            $recommendations->assessments,
            fn (GroupAssessment $assessment): bool => $date === null || $assessment->date->format('Y-m-d') === $date,
        ));
    }

    private function isCalendarDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function headline(PortfolioSnapshot $snapshot, RecommendationSet $recommendations, GroupAssessment $assessment): string
    {
        $groupName = $snapshot->group($assessment->groupId)->name ?? $assessment->groupId;

        $leaders = count(array_filter(
            $recommendations->forGroupOn($assessment->groupId, $assessment->date),
            fn (Recommendation $recommendation): bool => $recommendation->isPriceLeader(),
        ));

        $decision = match ($assessment->mode) {
            PricingMode::Fill => "{$leaders} ".($leaders === 1 ? 'price leader' : 'price leaders')." at up to {$this->percent($assessment->discountRate)}.",
            PricingMode::PassThrough => 'unchanged.',
            PricingMode::Hold, PricingMode::Protect => 'no discounts.',
        };

        return "{$groupName}, {$assessment->date->format('l Y-m-d')}: {$assessment->progress()}. {$assessment->mode->label()}, {$decision}";
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }

    private function percent(float $fraction): string
    {
        return $fraction > 0.0 ? (int) round($fraction * 100).'%' : '-';
    }
}
