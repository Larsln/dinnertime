<?php

namespace App\Services\Planning;

use App\Enums\MealType;
use App\Enums\TagType;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * Fills chosen days and meals of a period with recipes that use up each
 * other's leftovers.
 *
 * Starts from random plans and keeps swapping single meals while that lowers
 * the cost. With a few dozen recipes this finds a good plan quickly.
 */
class MealPlanGenerator
{
    private const int RESTARTS = 4;

    private const float REPEAT_PENALTY = 2.0;

    private const float RECENT_PENALTY = 1.0;

    private const int RECENT_DAYS = 14;

    private const float EPSILON = 1e-6;

    public function __construct(
        private readonly ShoppingForecaster $forecaster,
        private readonly PlanCost $planCost,
        private readonly Randomizer $randomizer,
    ) {}

    /**
     * Plan the requested days and meals that have no entry yet. All entries
     * of the period are kept and taken into account.
     *
     * @param  list<array{date: CarbonImmutable, meal: MealType}>  $requested
     * @return Collection<int, MealPlanEntry> The entries that were added.
     */
    public function generate(CarbonImmutable $from, CarbonImmutable $to, array $requested, int $servings): Collection
    {
        $existing = MealPlanEntry::query()
            ->with(array_map(fn (string $relation) => "recipe.{$relation}", ShoppingForecaster::RECIPE_RELATIONS))
            ->whereBetween('date', [$from, $to])
            ->get();

        $candidates = $this->candidates(array_unique(array_column($requested, 'meal'), SORT_REGULAR));
        $slots = $this->openSlots($requested, $existing, $candidates);

        $recipes = $this->search(
            $slots,
            $candidates,
            $servings,
            $existing->map(PlannedMeal::fromEntry(...))->all(),
            PantryItem::all(),
            $this->recentRecipeIds($from),
        );

        return DB::transaction(fn () => collect($slots)->map(
            fn (array $slot, int $index) => MealPlanEntry::create([
                'date' => $slot['date'],
                'meal' => $slot['meal'],
                'recipe_id' => $recipes[$index]->id,
                'servings' => $servings,
            ])->setRelation('recipe', $recipes[$index]),
        ));
    }

    /**
     * Recipes tagged for each requested meal.
     *
     * @param  list<MealType>  $meals
     * @return array<string, list<Recipe>> Recipes keyed by meal type value.
     */
    private function candidates(array $meals): array
    {
        $candidates = [];

        foreach ($meals as $meal) {
            $candidates[$meal->value] = Recipe::query()
                ->with(ShoppingForecaster::RECIPE_RELATIONS)
                ->whereHas('tags', fn (Builder $query) => $query
                    ->where('type', TagType::Meal)
                    ->where('key', $meal->value))
                ->get()
                ->all();
        }

        return $candidates;
    }

    /**
     * The requested slots that have no entry yet and at least one recipe.
     *
     * @param  list<array{date: CarbonImmutable, meal: MealType}>  $requested
     * @param  Collection<int, MealPlanEntry>  $existing
     * @param  array<string, list<Recipe>>  $candidates
     * @return list<array{date: CarbonImmutable, meal: MealType}>
     */
    private function openSlots(array $requested, Collection $existing, array $candidates): array
    {
        $taken = $existing
            ->mapWithKeys(fn (MealPlanEntry $entry) => ["{$entry->date->toDateString()}|{$entry->meal->value}" => true]);

        return array_values(array_filter(
            $requested,
            fn (array $slot) => ! $taken->has("{$slot['date']->toDateString()}|{$slot['meal']->value}")
                && $candidates[$slot['meal']->value] !== [],
        ));
    }

    /**
     * @return list<int>
     */
    private function recentRecipeIds(CarbonImmutable $from): array
    {
        return MealPlanEntry::query()
            ->whereBetween('date', [$from->subDays(self::RECENT_DAYS), $from->subDay()])
            ->pluck('recipe_id')
            ->all();
    }

    /**
     * Find the cheapest assignment of recipes to the open slots.
     *
     * @param  list<array{date: CarbonImmutable, meal: MealType}>  $slots
     * @param  array<string, list<Recipe>>  $candidates
     * @param  list<PlannedMeal>  $fixed
     * @param  Collection<int, PantryItem>  $pantry
     * @param  list<int>  $recentRecipeIds
     * @return list<Recipe> Recipes aligned with the slots.
     */
    private function search(array $slots, array $candidates, int $servings, array $fixed, Collection $pantry, array $recentRecipeIds): array
    {
        $cost = fn (array $recipes) => $this->cost($slots, $recipes, $servings, $fixed, $pantry, $recentRecipeIds);
        $best = [];
        $bestCost = INF;

        for ($restart = 0; $restart < self::RESTARTS; $restart++) {
            $recipes = $this->randomAssignment($slots, $candidates);
            $currentCost = $cost($recipes);

            do {
                $improved = false;

                foreach ($slots as $index => $slot) {
                    foreach ($candidates[$slot['meal']->value] as $candidate) {
                        $trial = array_replace($recipes, [$index => $candidate]);
                        $trialCost = $candidate->is($recipes[$index]) ? INF : $cost($trial);

                        if ($trialCost < $currentCost - self::EPSILON) {
                            $recipes = $trial;
                            $currentCost = $trialCost;
                            $improved = true;
                        }
                    }
                }
            } while ($improved);

            if ($currentCost < $bestCost) {
                $best = $recipes;
                $bestCost = $currentCost;
            }
        }

        return $best;
    }

    /**
     * Pick a random recipe per slot, avoiding repeats while unused ones remain.
     *
     * @param  list<array{date: CarbonImmutable, meal: MealType}>  $slots
     * @param  array<string, list<Recipe>>  $candidates
     * @return list<Recipe>
     */
    private function randomAssignment(array $slots, array $candidates): array
    {
        $used = [];
        $recipes = [];

        foreach ($slots as $slot) {
            $shuffled = $this->randomizer->shuffleArray($candidates[$slot['meal']->value]);
            $recipe = array_find($shuffled, fn (Recipe $recipe) => ! isset($used[$recipe->id])) ?? $shuffled[0];

            $used[$recipe->id] = true;
            $recipes[] = $recipe;
        }

        return $recipes;
    }

    /**
     * Leftover cost of the whole plan plus penalties for cooking a recipe
     * twice or again shortly after it was last planned.
     *
     * @param  list<array{date: CarbonImmutable, meal: MealType}>  $slots
     * @param  list<Recipe>  $recipes
     * @param  list<PlannedMeal>  $fixed
     * @param  Collection<int, PantryItem>  $pantry
     * @param  list<int>  $recentRecipeIds
     */
    private function cost(array $slots, array $recipes, int $servings, array $fixed, Collection $pantry, array $recentRecipeIds): float
    {
        $meals = $fixed;

        foreach ($slots as $index => $slot) {
            $meals[] = new PlannedMeal($slot['date'], $recipes[$index], $servings);
        }

        $recipeIds = array_map(fn (PlannedMeal $meal) => $meal->recipe->id, $meals);
        $repeats = count($recipeIds) - count(array_unique($recipeIds));
        $recent = count(array_intersect(array_map(fn (Recipe $recipe) => $recipe->id, $recipes), $recentRecipeIds));

        return $this->planCost->of($this->forecaster->forecast($meals, $pantry))
            + self::REPEAT_PENALTY * $repeats
            + self::RECENT_PENALTY * $recent;
    }
}
