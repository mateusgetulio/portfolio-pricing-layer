<?php

namespace App\Pricing\Data;

use DateTimeImmutable;

final readonly class RecommendationSet
{
    /**
     * @param  list<GroupAssessment>  $assessments
     * @param  list<Recommendation>  $recommendations
     */
    public function __construct(
        public array $assessments,
        public array $recommendations,
    ) {}

    public function assessmentFor(string $groupId, DateTimeImmutable $date): ?GroupAssessment
    {
        foreach ($this->assessments as $assessment) {
            if ($assessment->groupId === $groupId && $assessment->date->format('Y-m-d') === $date->format('Y-m-d')) {
                return $assessment;
            }
        }

        return null;
    }

    public function forUnitOn(string $unitId, DateTimeImmutable $date): ?Recommendation
    {
        foreach ($this->recommendations as $recommendation) {
            if ($recommendation->unitId === $unitId && $recommendation->date->format('Y-m-d') === $date->format('Y-m-d')) {
                return $recommendation;
            }
        }

        return null;
    }

    /**
     * @return list<Recommendation>
     */
    public function forGroupOn(string $groupId, DateTimeImmutable $date): array
    {
        return array_values(array_filter(
            $this->recommendations,
            fn (Recommendation $recommendation): bool => $recommendation->groupId === $groupId
                && $recommendation->date->format('Y-m-d') === $date->format('Y-m-d'),
        ));
    }
}
