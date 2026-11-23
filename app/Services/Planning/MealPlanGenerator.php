<?php

namespace App\Services\Planning;

use App\Enums\MealType;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
use Illuminate\Support\Collection;
use Random\Randomizer;

/**
 * Suggests recipes for chosen days and meals without saving them.
 *
 * Starts from random plans and keeps swapping single meals while that lowers
 * the cost, which combines food left over with the household's preferences.
 * With a few dozen recipes this finds a good plan quickly, and every call
 * can come up with a different one.
 */
class MealPlanGenerator
{
    private const int RESTARTS = 4;

    private const float EPSILON = 1e-6;

    public function __construct(
        private readonly CandidatePool $pool,
        private readonly ShoppingForecaster $forecaster,
        private readonly PlanCost $leftoverCost,
        private readonly PreferenceCost $preferenceCost,
        private readonly Randomizer $randomizer,
        private readonly PlanHistory $history,
        private readonly PlanExplainer $explainer,
    ) {}

    /**
     * Suggest a recipe for every requested slot that has no entry yet.
     * Entries of the period and recipes the user locked are kept and taken
     * into account. Slots without any matching recipe are left out.
     */
    public function suggest(PlanRequest $request): PlanSuggestion
    {
        $existing = MealPlanEntry::query()
            ->with(array_map(fn (string $relation) => "recipe.{$relation}", [...ShoppingForecaster::RECIPE_RELATIONS, 'tags']))
            ->whereBetween('date', [$request->from, $request->to])
            ->get();

        $taken = $existing->mapWithKeys(fn (MealPlanEntry $entry) => ["{$entry->date->toDateString()}|{$entry->meal->value}" => true]);
        $slots = array_values(array_filter($request->slots, fn (PlanSlot $slot) => ! $taken->has($slot->key())));

        $candidates = $this->pool->forRequest($request);
        $kept = $this->keptAssignments($slots, $request);
        $open = array_values(array_filter(
            $slots,
            fn (PlanSlot $slot) => ! isset($kept[$slot->key()]) && ($candidates[$slot->key()] ?? []) !== [],
        ));

        $existingMeals = $existing->map(PlannedMeal::fromEntry(...))->values()->all();
        $keptMeals = array_values(array_map(fn (SlotAssignment $assignment) => $this->meal($assignment->slot, $assignment->recipe, $request), $kept));

        $recipes = $this->search($open, $candidates, [...$existingMeals, ...$keptMeals], $request);

        $assignments = [
            ...array_values($kept),
            ...array_map(fn (PlanSlot $slot, Recipe $recipe) => new SlotAssignment($slot, $recipe, false), $open, $recipes),
        ];

        return new PlanSuggestion(
            $this->inPlanOrder($this->explained($assignments, $existingMeals, $request)),
            collect($candidates)->flatten()->unique('id')->count(),
        );
    }

    /**
     * The assignments with the reasons they fit the whole plan.
     *
     * @param  list<SlotAssignment>  $assignments
     * @param  list<PlannedMeal>  $existingMeals
     * @return list<SlotAssignment>
     */
    private function explained(array $assignments, array $existingMeals, PlanRequest $request): array
    {
        $meals = array_map(fn (SlotAssignment $assignment) => $this->meal($assignment->slot, $assignment->recipe, $request), $assignments);
        $reasons = array_slice($this->explainer->explain([...$existingMeals, ...$meals], $request->from, $request->user, $request->preferences), count($existingMeals));

        return array_map(fn (SlotAssignment $assignment, array $reasons) => $assignment->withReasons($reasons), $assignments, $reasons);
    }

    /**
     * Recipes the user locked for open slots.
     *
     * @param  list<PlanSlot>  $slots
     * @return array<string, SlotAssignment> Assignments keyed by slot key.
     */
    private function keptAssignments(array $slots, PlanRequest $request): array
    {
        $recipes = Recipe::query()
            ->withMarksOf($request->user)
            ->with([...ShoppingForecaster::RECIPE_RELATIONS, 'tags'])
            ->findMany(array_values($request->keep))
            ->keyBy('id');

        $kept = [];

        foreach ($slots as $slot) {
            $recipe = $recipes->get($request->keep[$slot->key()] ?? null);

            if ($recipe !== null) {
                $kept[$slot->key()] = new SlotAssignment($slot, $recipe, true);
            }
        }

        return $kept;
    }

    /**
     * Find the cheapest assignment of recipes to the open slots.
     *
     * @param  list<PlanSlot>  $slots
     * @param  array<string, list<Recipe>>  $candidates
     * @param  list<PlannedMeal>  $fixed
     * @return list<Recipe> Recipes aligned with the slots.
     */
    private function search(array $slots, array $candidates, array $fixed, PlanRequest $request): array
    {
        $pantry = PantryItem::all();
        $daysSincePlanned = $this->history->daysSincePlanned($request->from);
        $cost = fn (array $recipes) => $this->cost($slots, $recipes, $fixed, $pantry, $daysSincePlanned, $request);

        $best = [];
        $bestCost = INF;

        for ($restart = 0; $restart < self::RESTARTS; $restart++) {
            $recipes = $this->randomAssignment($slots, $candidates);
            $currentCost = $cost($recipes);

            do {
                $improved = false;

                foreach ($slots as $index => $slot) {
                    foreach ($candidates[$slot->key()] as $candidate) {
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
     * @param  list<PlanSlot>  $slots
     * @param  array<string, list<Recipe>>  $candidates
     * @return list<Recipe>
     */
    private function randomAssignment(array $slots, array $candidates): array
    {
        $used = [];
        $recipes = [];

        foreach ($slots as $slot) {
            $shuffled = $this->randomizer->shuffleArray($candidates[$slot->key()]);
            $recipe = array_find($shuffled, fn (Recipe $recipe) => ! isset($used[$recipe->id])) ?? $shuffled[0];

            $used[$recipe->id] = true;
            $recipes[] = $recipe;
        }

        return $recipes;
    }

    /**
     * Cost of the whole plan: food left over, unless the household does not
     * care, plus how far the plan is from its preferences.
     *
     * @param  list<PlanSlot>  $slots
     * @param  list<Recipe>  $recipes
     * @param  list<PlannedMeal>  $fixed
     * @param  Collection<int, PantryItem>  $pantry
     * @param  array<int, int>  $daysSincePlanned
     */
    private function cost(array $slots, array $recipes, array $fixed, Collection $pantry, array $daysSincePlanned, PlanRequest $request): float
    {
        $meals = [
            ...$fixed,
            ...array_map(fn (PlanSlot $slot, Recipe $recipe) => $this->meal($slot, $recipe, $request), $slots, $recipes),
        ];

        $leftovers = $request->preferences->useLeftovers
            ? $this->leftoverCost->of($this->forecaster->forecast($meals, $pantry))
            : 0;

        return $leftovers + $this->preferenceCost->of($meals, $recipes, $request->preferences, $daysSincePlanned);
    }

    private function meal(PlanSlot $slot, Recipe $recipe, PlanRequest $request): PlannedMeal
    {
        return new PlannedMeal($slot->date, $recipe, $request->servings);
    }

    /**
     * @param  list<SlotAssignment>  $assignments
     * @return list<SlotAssignment>
     */
    private function inPlanOrder(array $assignments): array
    {
        $mealOrder = array_flip(array_column(MealType::cases(), 'value'));

        usort($assignments, fn (SlotAssignment $a, SlotAssignment $b) => [$a->slot->date, $mealOrder[$a->slot->meal->value]]
            <=> [$b->slot->date, $mealOrder[$b->slot->meal->value]]);

        return $assignments;
    }
}
