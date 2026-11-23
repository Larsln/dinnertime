<?php

namespace App\Services\Planning;

use App\Enums\TagType;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds the recipes that may be suggested for each slot of a request.
 */
class CandidatePool
{
    private const int QUICK_MINUTES = 30;

    private const string QUICK_TAG = 'quick';

    /**
     * Candidates per slot. A filter that would leave a slot without any
     * recipe is not applied to that slot.
     *
     * @return array<string, list<Recipe>> Recipes keyed by slot key.
     */
    public function forRequest(PlanRequest $request): array
    {
        $byMeal = [];
        $candidates = [];

        foreach ($request->slots as $slot) {
            $recipes = $byMeal[$slot->meal->value] ??= $this->recipesForMeal($slot->meal->value, $request);

            if ($request->preferences->quickOnWeekdays && $slot->isOnWeekday()) {
                $recipes = $this->filtered($recipes, self::isQuick(...));
            }

            $avoided = $request->avoid[$slot->key()] ?? null;

            $candidates[$slot->key()] = $this->filtered($recipes, fn (Recipe $recipe) => $recipe->id !== $avoided);
        }

        return $candidates;
    }

    /**
     * Recipes tagged for a meal that pass the tag and course filters.
     *
     * @return list<Recipe>
     */
    private function recipesForMeal(string $meal, PlanRequest $request): array
    {
        return Recipe::query()
            ->withMarksOf($request->user)
            ->with([...ShoppingForecaster::RECIPE_RELATIONS, 'tags'])
            ->whereHas('tags', fn (Builder $query) => $query->where('type', TagType::Meal)->where('key', $meal))
            ->when($request->tagIds !== [], fn (Builder $query) => $query
                ->whereHas('tags', fn (Builder $query) => $query->whereIn('tags.id', $request->tagIds)))
            ->when($request->courses !== [], fn (Builder $query) => $query->whereIn('course', $request->courses))
            ->get()
            ->all();
    }

    /**
     * Keep the recipes that pass the test, or all of them when none does.
     *
     * @param  list<Recipe>  $recipes
     * @param  callable(Recipe): bool  $test
     * @return list<Recipe>
     */
    private function filtered(array $recipes, callable $test): array
    {
        return array_values(array_filter($recipes, $test)) ?: $recipes;
    }

    /**
     * Whether a recipe takes at most half an hour or is tagged as quick.
     */
    public static function isQuick(Recipe $recipe): bool
    {
        $minutes = $recipe->prep_minutes + $recipe->cook_minutes;

        return ($minutes > 0 && $minutes <= self::QUICK_MINUTES)
            || $recipe->tags->contains(fn ($tag) => $tag->key === self::QUICK_TAG);
    }
}
