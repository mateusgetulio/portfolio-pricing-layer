<?php

namespace App\Http\Resources;

use App\Pricing\Data\GroupAssessment;
use App\Pricing\Data\PortfolioSnapshot;
use App\Pricing\Data\Recommendation;
use App\Pricing\Data\RecommendationSet;
use App\Pricing\Enums\DayType;
use App\Pricing\Enums\PricingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PricedScenarioResource extends JsonResource
{
    public function __construct(
        private PortfolioSnapshot $snapshot,
        private RecommendationSet $recommendations,
    ) {
        parent::__construct($recommendations);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->recommendations->assessments[0];
        $nights = $this->recommendations->forGroupOn($assessment->groupId, $assessment->date);
        $leaders = array_values(array_filter($nights, fn (Recommendation $recommendation): bool => $recommendation->isPriceLeader()));
        usort($leaders, fn (Recommendation $first, Recommendation $second): int => $first->leaderRank <=> $second->leaderRank);

        return [
            'night' => $assessment->date->format('Y-m-d'),
            'day_type' => DayType::forNight($assessment->date)->value,
            'lead_time_days' => $assessment->leadTimeDays,
            'assessment' => [
                'booked' => $assessment->bookedUnits,
                'available' => $assessment->availableUnits,
                'sellable' => $assessment->sellableUnits(),
                'occupancy' => round($assessment->occupancy, 4),
                'target' => round($assessment->target, 4),
                'target_booked' => $assessment->targetBooked,
                'mode' => $assessment->mode->value,
                'mode_label' => $assessment->mode->label(),
                'leader_budget' => $assessment->leaderBudget,
                'discount_rate' => $assessment->discountRate,
                'leaders' => count($leaders),
            ],
            'answers' => [
                'where' => "{$assessment->progress()}.",
                'what' => $this->whatWeAreDoing($assessment, count($leaders)),
                'why' => $this->whyTheseUnits($assessment, count($leaders)),
            ],
            'comparison' => [
                'without_layer' => $this->withoutLayer($assessment),
                'with_layer' => $this->withLayer($assessment, $leaders),
            ],
            'units' => $this->units($nights),
        ];
    }

    /**
     * @param  list<Recommendation>  $nights
     * @return list<array<string, mixed>>
     */
    private function units(array $nights): array
    {
        $names = [];

        foreach ($this->snapshot->units as $unit) {
            $names[$unit->id] = $unit->name;
        }

        return array_map(fn (Recommendation $recommendation): array => [
            'id' => $recommendation->unitId,
            'name' => $names[$recommendation->unitId],
            'status' => $recommendation->status->value,
            'booking_strength' => round($recommendation->bookingStrength, 2),
            'base_price_cents' => $recommendation->basePriceCents,
            'recommended_price_cents' => $recommendation->recommendedPriceCents,
            'discount' => round($recommendation->discount(), 4),
            'rule' => $recommendation->rule->value,
            'leader_rank' => $recommendation->leaderRank,
            'reason' => $recommendation->reason,
        ], $nights);
    }

    private function whatWeAreDoing(GroupAssessment $assessment, int $leaders): string
    {
        $held = $assessment->availableUnits - $leaders;

        return match (true) {
            $assessment->availableUnits === 0 => 'No discounts, every sellable unit is booked.',
            $assessment->mode === PricingMode::Fill && $held === 0 => Str::plural('price leader', $leaders, prependCount: true).'.',
            $assessment->mode === PricingMode::Fill => Str::plural('price leader', $leaders, prependCount: true).', '.Str::plural('unit', $held, prependCount: true).' held.',
            $assessment->mode === PricingMode::Protect => 'No discounts, '.Str::plural('unit', $held, prependCount: true).' protected.',
            $assessment->mode === PricingMode::PassThrough => 'No discounts, '.Str::plural('unit', $held, prependCount: true).' unchanged.',
            default => 'No discounts, '.Str::plural('unit', $held, prependCount: true).' held.',
        };
    }

    private function whyTheseUnits(GroupAssessment $assessment, int $leaders): string
    {
        return match (true) {
            $assessment->availableUnits === 0 => 'No unit is available, so there is nothing to reprice.',
            $assessment->mode === PricingMode::Fill && $leaders === $assessment->availableUnits => 'The leader budget covers every available unit, so all of them lead.',
            $assessment->mode === PricingMode::Fill => 'They have the weakest recent booking strength in the group.',
            $assessment->mode === PricingMode::Hold => 'The group is on pace, so no unit needs a discount.',
            $assessment->mode === PricingMode::Protect => 'The group is ahead of pace, so every price is protected.',
            default => 'The group is too small for portfolio pricing.',
        };
    }

    private function withoutLayer(GroupAssessment $assessment): string
    {
        return match ($assessment->availableUnits) {
            0 => 'No unit is available, so nothing changes.',
            1 => '1 available unit keeps its independent price.',
            default => "{$assessment->availableUnits} available units keep their independent prices.",
        };
    }

    /**
     * @param  list<Recommendation>  $leaders
     */
    private function withLayer(GroupAssessment $assessment, array $leaders): string
    {
        if ($leaders === []) {
            return 'No unit is discounted.';
        }

        $prices = array_map($this->leaderPrice(...), $leaders);
        $last = array_pop($prices);
        $list = $prices === [] ? $last : implode(', ', $prices)." and {$last}";
        $held = $assessment->availableUnits - count($leaders);

        $sentence = count($leaders) === 1
            ? "1 becomes a price leader at {$list}"
            : count($leaders)." become price leaders at {$list}";

        return $held === 0 ? "{$sentence}." : "{$sentence}, {$held} held.";
    }

    private function leaderPrice(Recommendation $leader): string
    {
        $percent = (int) round($leader->discount() * 100);

        return match (true) {
            $leader->recommendedPriceCents > $leader->basePriceCents => 'the floor price',
            $leader->recommendedPriceCents === $leader->basePriceCents => 'an unchanged price',
            $percent === 0 => 'less than 1%',
            default => "{$percent}%",
        };
    }
}
