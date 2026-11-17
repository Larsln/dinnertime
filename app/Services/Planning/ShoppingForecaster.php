<?php

namespace App\Services\Planning;

use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Product;
use App\Models\Recipe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use WeakMap;

/**
 * Works out what has to be bought for planned meals, given the pantry.
 */
class ShoppingForecaster
{
    /**
     * Relations a recipe needs to have loaded to be forecast.
     */
    public const array RECIPE_RELATIONS = [
        'ingredients.unit',
        'ingredients.product.conversions.unit',
        'ingredients.product.packages',
    ];

    /**
     * Converted ingredient lines per recipe, kept while the recipe is in use.
     *
     * @var WeakMap<Recipe, list<array{product: ?Product, amount: ?ConvertedAmount, text: ?string}>>
     */
    private WeakMap $recipeLines;

    public function __construct(
        private readonly IngredientConverter $converter,
        private readonly ProductLedger $ledger,
    ) {
        $this->recipeLines = new WeakMap;
    }

    /**
     * Forecast the meals planned between two days against the current pantry.
     */
    public function forecastPeriod(CarbonImmutable $from, CarbonImmutable $to): ShoppingForecast
    {
        $meals = MealPlanEntry::query()
            ->with(array_map(fn (string $relation) => "recipe.{$relation}", self::RECIPE_RELATIONS))
            ->whereBetween('date', [$from, $to])
            ->get()
            ->map(PlannedMeal::fromEntry(...))
            ->all();

        return $this->forecast($meals, PantryItem::all());
    }

    /**
     * @param  list<PlannedMeal>  $meals
     * @param  Collection<int, PantryItem>  $pantry
     */
    public function forecast(array $meals, Collection $pantry): ShoppingForecast
    {
        usort($meals, fn (PlannedMeal $a, PlannedMeal $b) => $a->date <=> $b->date);

        $products = [];
        $demands = [];
        $approximate = [];
        $unquantified = [];
        $unresolved = [];

        foreach ($meals as $meal) {
            $scale = $meal->recipe->servings ? $meal->servings / $meal->recipe->servings : 1;

            foreach ($this->linesOf($meal->recipe) as ['product' => $product, 'amount' => $amount, 'text' => $text]) {
                if ($product === null) {
                    $unresolved[$text] = true;
                } elseif (! $product->is_pantry_staple) {
                    $products[$product->id] = $product;

                    if ($amount === null) {
                        $unquantified[$product->id] = true;
                    } else {
                        $demands[$product->id][] = new Demand($meal->date, $meal->recipe, $amount->amount * $scale);
                        $approximate[$product->id] = ($approximate[$product->id] ?? false) || $amount->approximate;
                    }
                }
            }
        }

        $lots = $pantry->groupBy('product_id');

        return new ShoppingForecast(
            array_values(array_map(fn (Product $product) => $this->ledger->forecast(
                $product,
                $demands[$product->id] ?? [],
                $this->lotsOf($lots->get($product->id, collect())),
                $approximate[$product->id] ?? false,
                $unquantified[$product->id] ?? false,
            ), $products)),
            array_keys($unresolved),
        );
    }

    /**
     * Ingredient lines of a recipe that name a product or carry a text.
     *
     * @return list<array{product: ?Product, amount: ?ConvertedAmount, text: ?string}>
     */
    private function linesOf(Recipe $recipe): array
    {
        if (isset($this->recipeLines[$recipe])) {
            return $this->recipeLines[$recipe];
        }

        $lines = [];

        foreach ($recipe->ingredients as $ingredient) {
            $text = $ingredient->raw_text ?? $ingredient->note;

            if ($ingredient->product !== null || filled($text)) {
                $lines[] = [
                    'product' => $ingredient->product,
                    'amount' => $this->converter->convert($ingredient),
                    'text' => $text,
                ];
            }
        }

        return $this->recipeLines[$recipe] = $lines;
    }

    /**
     * @param  Collection<int, PantryItem>  $items
     * @return list<StockLot>
     */
    private function lotsOf(Collection $items): array
    {
        return $items
            ->map(fn (PantryItem $item) => new StockLot($item->amount, $item->opened_on, $item->expires_on))
            ->values()
            ->all();
    }
}
