<?php

namespace App\Services\Planning;

use App\Models\MealPlanEntry;
use Carbon\CarbonImmutable;

/**
 * What was planned before a period.
 */
class PlanHistory
{
    /**
     * Days since each recipe was last planned before the period, looking
     * back as far as the planner remembers.
     *
     * @return array<int, int> Days keyed by recipe id.
     */
    public function daysSincePlanned(CarbonImmutable $from): array
    {
        return MealPlanEntry::query()
            ->whereBetween('date', [$from->subDays(PreferenceCost::MEMORY_DAYS), $from->subDay()])
            ->orderBy('date')
            ->get(['recipe_id', 'date'])
            ->mapWithKeys(fn (MealPlanEntry $entry) => [$entry->recipe_id => (int) $entry->date->diffInDays($from)])
            ->all();
    }
}
