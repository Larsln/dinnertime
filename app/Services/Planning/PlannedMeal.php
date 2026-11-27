<?php

namespace App\Services\Planning;

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use Carbon\CarbonImmutable;

/**
 * Recipe cooked on a day for a number of servings, which need not be whole.
 * A meal and its sides are eaten together and carry the same `$table`.
 */
final readonly class PlannedMeal
{
    public function __construct(
        public CarbonImmutable $date,
        public Recipe $recipe,
        public float $servings,
        public ?int $table = null,
    ) {}

    public static function fromEntry(MealPlanEntry $entry): self
    {
        return new self($entry->date, $entry->recipe, $entry->portions ?? $entry->servings, $entry->parent_id ?? $entry->id);
    }
}
