<?php

namespace App\Services\Planning;

/**
 * Proposed plan that has not been saved yet.
 */
final readonly class PlanSuggestion
{
    /**
     * @param  list<SlotAssignment>  $assignments  Ordered by day and meal.
     * @param  int  $candidateCount  Number of recipes that were eligible for at least one slot.
     */
    public function __construct(
        public array $assignments,
        public int $candidateCount,
    ) {}
}
