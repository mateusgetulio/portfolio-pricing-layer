<?php

namespace App\Pricing;

use App\Pricing\Data\Unit;

final readonly class LeaderSelector
{
    /**
     * @param  list<Unit>  $availableUnits
     * @param  array<array-key, float>  $strengths
     * @return list<Unit>
     */
    public function select(array $availableUnits, array $strengths, int $leaderBudget): array
    {
        usort($availableUnits, function (Unit $first, Unit $second) use ($strengths): int {
            return ($strengths[$first->id] ?? 1.0) <=> ($strengths[$second->id] ?? 1.0)
                ?: strcmp($first->id, $second->id);
        });

        return array_slice($availableUnits, 0, max(0, $leaderBudget));
    }
}
