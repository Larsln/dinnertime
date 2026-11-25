<?php

namespace App\Services\Dinner;

use App\Models\PantryItem;
use App\Models\Recipe;
use App\Services\Planning\IngredientConverter;
use Illuminate\Support\Collection;

/**
 * Tells what the household would have to buy to cook a recipe right now.
 */
class RecipeAvailability
{
    public function __construct(private readonly IngredientConverter $converter) {}

    /**
     * Number of products the pantry does not cover for the given servings.
     * Staples count as present. A product whose amount cannot be compared
     * counts as present as soon as the pantry holds any of it.
     *
     * @param  Collection<int, PantryItem>  $pantry
     */
    public function missingCount(Recipe $recipe, int $servings, Collection $pantry): int
    {
        $scale = $recipe->servings > 0 ? $servings / $recipe->servings : 1;
        $stock = $pantry->groupBy('product_id')->map(fn (Collection $items) => (float) $items->sum('amount'));
        $needed = [];

        foreach ($recipe->ingredients as $ingredient) {
            if ($ingredient->product === null || $ingredient->product->isStaple()) {
                continue;
            }

            $amount = $this->converter->convert($ingredient)?->amount;
            $needed[$ingredient->product_id] = ($needed[$ingredient->product_id] ?? 0) + ($amount ?? 0) * $scale;
        }

        return count(array_filter(
            $needed,
            fn (float $amount, int $productId) => ! $stock->has($productId) || $amount > $stock[$productId] + 0.001,
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
