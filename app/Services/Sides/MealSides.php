<?php

namespace App\Services\Sides;

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Services\Portions\PortionPlanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the sides of a planned meal with it.
 *
 * A side is an entry of its own that hangs on the meal it is served
 * with. It is eaten on the same day at the same meal by the same people,
 * each with the portion of the side that suits them.
 */
class MealSides
{
    public function __construct(private readonly PortionPlanner $portions) {}

    /**
     * Recipes that may be served with a dish, those served with it most
     * recently first.
     *
     * @return Builder<Recipe>
     */
    public function optionsFor(Recipe $dish): Builder
    {
        $lastServed = MealPlanEntry::query()
            ->from('meal_plan_entries as sides')
            ->join('meal_plan_entries as mains', 'mains.id', '=', 'sides.parent_id')
            ->where('mains.recipe_id', $dish->id)
            ->whereColumn('sides.recipe_id', 'recipes.id')
            ->selectRaw('max(sides.date)');

        return Recipe::query()
            ->whereIn('side_kind', $dish->side_options ?? [])
            ->whereKeyNot($dish->id)
            ->orderByRaw("({$lastServed->toSql()}) desc nulls last", $lastServed->getBindings())
            ->orderBy('title');
    }

    /**
     * Serve a side with a planned meal.
     *
     * @throws ValidationException When the dish is not served with sides of that kind.
     */
    public function attach(MealPlanEntry $main, Recipe $side): MealPlanEntry
    {
        if ($main->parent_id !== null || ! $this->accepts($main->recipe, $side)) {
            throw ValidationException::withMessages(['recipe_id' => __('messages.side_not_accepted')]);
        }

        $entry = new MealPlanEntry(['parent_id' => $main->id, 'recipe_id' => $side->id]);
        $this->seat($main, $entry);
        $entry->cooked_at = $main->cooked_at;
        $entry->save();

        return $entry;
    }

    /**
     * Let the sides follow what is about to change on their meal. Call
     * it before the meal is saved. Sides a new dish is not served with
     * are dropped.
     */
    public function follow(MealPlanEntry $main): void
    {
        $changesRecipe = $main->isDirty('recipe_id');
        $changesCooked = $main->isDirty('cooked_at');
        $changesSeats = $main->isDirty(['date', 'meal', 'servings', 'eaters', 'guests']);

        if ($main->parent_id !== null || ! ($changesRecipe || $changesCooked || $changesSeats)) {
            return;
        }

        $dish = $changesRecipe ? Recipe::find($main->recipe_id) : null;

        foreach ($main->sides()->with('recipe')->get() as $side) {
            if ($dish !== null && ! $this->accepts($dish, $side->recipe)) {
                $side->delete();

                continue;
            }

            if ($changesSeats) {
                $this->seat($main, $side);
            }

            if ($changesCooked) {
                $side->cooked_at = $main->cooked_at;
            }

            $side->save();
        }
    }

    /**
     * Whether a dish is served with sides of the kind of a recipe.
     */
    private function accepts(Recipe $dish, Recipe $side): bool
    {
        return $side->side_kind !== null && in_array($side->side_kind->value, $dish->side_options ?? [], true);
    }

    /**
     * Give a side the day, the meal and the eaters of its meal.
     */
    private function seat(MealPlanEntry $main, MealPlanEntry $side): void
    {
        $side->fill(['date' => $main->date, 'meal' => $main->meal, 'servings' => $main->servings]);

        if ($main->eaters === null) {
            $side->fill(['eaters' => null, 'guests' => 0, 'portions' => $main->servings]);

            return;
        }

        $this->portions->compose($side, array_column($main->eaters, 'user_id'), $main->guests ?? 0);
    }
}
