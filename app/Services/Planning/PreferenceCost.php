<?php

namespace App\Services\Planning;

use App\Models\Recipe;

/**
 * Rates how well a plan matches the household's preferences. Lower is
 * better; one point weighs as much as one wasted package.
 */
class PreferenceCost
{
    private const float REPEAT_PENALTY = 2.0;

    private const float REPEAT_PENALTY_WITH_VARIETY = 6.0;

    private const float SAME_CUISINE_PENALTY = 1.0;

    private const float RECENT_PENALTY = 1.0;

    private const int RECENT_DAYS = 14;

    private const float NOT_RECENT_WEIGHT = 3.0;

    private const float FAVORITE_BONUS = 1.0;

    private const float NEUTRAL_RATING = 5.0;

    private const array CUISINES = ['asian', 'italian', 'mexican', 'middle_eastern', 'german'];

    /**
     * Days over which a recipe counts as planned before.
     */
    public const int MEMORY_DAYS = 60;

    /**
     * @param  list<PlannedMeal>  $meals  All meals of the period, fixed and suggested.
     * @param  list<Recipe>  $suggested  Recipes the generator chose.
     * @param  array<int, int>  $daysSincePlanned  Days since each recipe was last planned, keyed by recipe id.
     */
    public function of(array $meals, array $suggested, PlanPreferences $preferences, array $daysSincePlanned): float
    {
        $cost = $this->repeats($meals) * ($preferences->variety ? self::REPEAT_PENALTY_WITH_VARIETY : self::REPEAT_PENALTY);

        if ($preferences->variety) {
            $cost += $this->sameCuisineOnConsecutiveDays($meals) * self::SAME_CUISINE_PENALTY;
        }

        foreach ($suggested as $recipe) {
            $cost += $this->recency($daysSincePlanned[$recipe->id] ?? null, $preferences);

            if ($preferences->preferFavorites) {
                $cost -= $this->appeal($recipe);
            }
        }

        return $cost;
    }

    /**
     * Number of meals that repeat a recipe already in the plan.
     *
     * @param  list<PlannedMeal>  $meals
     */
    private function repeats(array $meals): int
    {
        $recipeIds = array_map(fn (PlannedMeal $meal) => $meal->recipe->id, $meals);

        return count($recipeIds) - count(array_unique($recipeIds));
    }

    /**
     * Number of cuisines that appear on a day and again on the next.
     *
     * @param  list<PlannedMeal>  $meals
     */
    private function sameCuisineOnConsecutiveDays(array $meals): int
    {
        $cuisinesByDay = [];

        foreach ($meals as $meal) {
            $day = $meal->date->toDateString();
            $cuisines = $meal->recipe->tags->pluck('key')->intersect(self::CUISINES)->all();
            $cuisinesByDay[$day] = [...($cuisinesByDay[$day] ?? []), ...$cuisines];
        }

        $count = 0;

        foreach ($meals as $meal) {
            $nextDay = $meal->date->addDay()->toDateString();
            $cuisines = $meal->recipe->tags->pluck('key')->intersect(self::CUISINES)->all();
            $count += count(array_intersect($cuisines, $cuisinesByDay[$nextDay] ?? []));
        }

        return $count;
    }

    /**
     * Penalty for a recipe that was planned before: the more recent, the
     * higher when long-uncooked recipes are preferred, otherwise a flat
     * penalty within two weeks.
     */
    private function recency(?int $daysSince, PlanPreferences $preferences): float
    {
        if ($daysSince === null) {
            return 0;
        }

        if ($preferences->preferNotRecent) {
            return self::NOT_RECENT_WEIGHT * (1 - $daysSince / self::MEMORY_DAYS);
        }

        return $daysSince <= self::RECENT_DAYS ? self::RECENT_PENALTY : 0;
    }

    /**
     * How much the household likes a recipe: a bonus for the user's
     * favorites plus the average rating relative to the middle of the scale.
     */
    private function appeal(Recipe $recipe): float
    {
        $favorite = $recipe->marks->first()?->is_favorite ? self::FAVORITE_BONUS : 0;
        $average = $recipe->getAttribute('average_rating');

        return $favorite + (((float) ($average ?? self::NEUTRAL_RATING)) - self::NEUTRAL_RATING) / self::NEUTRAL_RATING;
    }
}
