<?php

namespace Tests\Support;

use App\Enums\ReviewStatus;
use App\Enums\TagType;
use App\Enums\ValueSource;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;

/**
 * Helpers for building products and recipes in planning tests.
 *
 * Requires the reference data to be seeded.
 */
trait BuildsCatalog
{
    /**
     * Create a product measured in grams that is sold everywhere in the
     * given package sizes.
     *
     * @param  list<int>  $packageSizes
     * @param  array<string, mixed>  $attributes
     */
    protected function product(string $name, array $packageSizes = [], array $attributes = []): Product
    {
        $product = Product::factory()->create(['name' => $name, ...$attributes]);

        foreach ($packageSizes as $size) {
            $product->offers()->create(['amount' => $size, 'unit' => $product->base_unit->value]);
        }

        return $product;
    }

    /**
     * Create a dinner recipe for two servings.
     *
     * @param  list<array{0: Product, 1: ?float, 2?: ?string}>  $ingredients  Product, quantity and unit key.
     */
    protected function recipe(string $title, array $ingredients, string $meal = 'dinner'): Recipe
    {
        $recipe = Recipe::factory()->create(['title' => $title, 'servings' => 2]);
        $recipe->tags()->attach(Tag::where('type', TagType::Meal)->where('key', $meal)->value('id'));

        foreach ($ingredients as $index => $ingredient) {
            $recipe->ingredients()->create([
                'position' => $index + 1,
                'product_id' => $ingredient[0]->id,
                'quantity' => $ingredient[1],
                'unit_id' => isset($ingredient[2]) ? Unit::where('key', $ingredient[2])->value('id') : null,
            ]);
        }

        return $recipe;
    }

    protected function convert(Product $product, string $unitKey, float $amountInBase, ReviewStatus $status = ReviewStatus::Confirmed): void
    {
        $product->conversions()->create([
            'unit_id' => Unit::where('key', $unitKey)->value('id'),
            'amount_in_base' => $amountInBase,
            'status' => $status,
            'source' => ValueSource::Ai,
        ]);
    }
}
