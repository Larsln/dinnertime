<?php

namespace App\Services\Planning;

use App\Models\PantryItem;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Tells why the meals of a plan go together: which packages they share,
 * what they use up from the pantry, and what speaks for each recipe on
 * its own. Reasons are plain data the clients put into words.
 */
class PlanExplainer
{
    private const int MAX_NAMES = 3;

    /**
     * Average rating, on the scale from 0 to 10, from which a recipe
     * counts as well rated.
     */
    private const float GOOD_RATING = 7.0;

    public function __construct(
        private readonly ShoppingForecaster $forecaster,
        private readonly PlanHistory $history,
    ) {}

    /**
     * @param  list<PlannedMeal>  $meals  All meals of the period.
     * @param  ?PlanPreferences  $preferences  What the plan was asked to favor, if known.
     * @return list<list<array<string, mixed>>> Reasons aligned with the meals.
     */
    public function explain(array $meals, CarbonImmutable $from, User $user, ?PlanPreferences $preferences = null): array
    {
        $pantry = PantryItem::with('product')->where('amount', '>', 0)->get()
            ->reject(fn (PantryItem $item) => $item->product->isStaple());
        $shares = $this->shares($meals, $pantry);
        $days = $this->history->daysSincePlanned($from);
        $recipes = Recipe::query()->withMarksOf($user)->findMany(array_map(fn (PlannedMeal $meal) => $meal->recipe->id, $meals))->keyBy('id');

        return array_map(fn (PlannedMeal $meal) => array_values(array_filter([
            $shares[$this->key($meal->date, $meal->recipe)] ?? null,
            $this->pantry($meal, $pantry),
            ...$this->appeal($recipes->get($meal->recipe->id)),
            $this->recency($days[$meal->recipe->id] ?? null),
            $this->quick($meal, $preferences),
        ])), $meals);
    }

    /**
     * Products several recipes draw from, most perishable first, with the
     * meals each meal shares them with. The same recipe planned twice
     * shares nothing with itself.
     *
     * @param  list<PlannedMeal>  $meals
     * @param  Collection<int, PantryItem>  $pantry
     * @return array<string, array<string, mixed>> Reasons keyed by meal.
     */
    private function shares(array $meals, Collection $pantry): array
    {
        $products = collect($this->forecaster->forecast($meals, $pantry)->products)
            ->sortBy(fn (ProductForecast $forecast) => $forecast->product->shelf_life_opened_days ?? PHP_INT_MAX);
        $shared = [];

        foreach ($products as $product) {
            $uses = collect($product->uses)->keyBy(fn (Demand $use) => $this->key($use->date, $use->recipe));

            foreach ($uses as $key => $use) {
                $others = $uses->reject(fn (Demand $other) => $other->recipe->is($use->recipe));

                if ($others->isNotEmpty()) {
                    $shared[$key]['products'][] = $product->product->name;
                }

                foreach ($others as $otherKey => $other) {
                    $shared[$key]['with'][$otherKey] ??= ['title' => $other->recipe->title, 'date' => $other->date->toDateString()];
                }
            }
        }

        return array_map(fn (array $share) => [
            'type' => 'shares',
            'products' => array_slice($share['products'], 0, self::MAX_NAMES),
            'with' => array_slice(array_values($share['with']), 0, self::MAX_NAMES),
        ], $shared);
    }

    /**
     * Products of the meal the pantry holds, those expiring first first.
     *
     * @param  Collection<int, PantryItem>  $pantry
     * @return ?array<string, mixed>
     */
    private function pantry(PlannedMeal $meal, Collection $pantry): ?array
    {
        $names = $pantry
            ->whereIn('product_id', $meal->recipe->ingredients->pluck('product_id')->filter())
            ->sortBy(fn (PantryItem $item) => $item->expires_on?->getTimestamp() ?? PHP_INT_MAX)
            ->map(fn (PantryItem $item) => $item->product->name)
            ->unique()
            ->take(self::MAX_NAMES)
            ->values()
            ->all();

        return $names === [] ? null : ['type' => 'pantry', 'products' => $names];
    }

    /**
     * Whether the user marked the recipe as favorite and whether the
     * household rates it well.
     *
     * @return list<array<string, mixed>>
     */
    private function appeal(?Recipe $recipe): array
    {
        $average = $recipe?->getAttribute('average_rating');

        return array_values(array_filter([
            $recipe?->marks->first()?->is_favorite ? ['type' => 'favorite'] : null,
            $average !== null && (float) $average >= self::GOOD_RATING ? ['type' => 'rating', 'value' => round((float) $average, 1)] : null,
        ]));
    }

    /**
     * When the recipe was last planned before the period.
     *
     * @return array<string, mixed>
     */
    private function recency(?int $days): array
    {
        return $days === null ? ['type' => 'not_recent'] : ['type' => 'last_planned', 'days' => $days];
    }

    /**
     * A quick recipe on a weekday, when the plan was asked for those.
     *
     * @return ?array<string, mixed>
     */
    private function quick(PlannedMeal $meal, ?PlanPreferences $preferences): ?array
    {
        if (! ($preferences?->quickOnWeekdays && $meal->date->isWeekday() && CandidatePool::isQuick($meal->recipe))) {
            return null;
        }

        $minutes = $meal->recipe->prep_minutes + $meal->recipe->cook_minutes;

        return ['type' => 'quick', 'minutes' => $minutes > 0 ? $minutes : null];
    }

    private function key(CarbonImmutable $date, Recipe $recipe): string
    {
        return "{$date->toDateString()}|{$recipe->id}";
    }
}
