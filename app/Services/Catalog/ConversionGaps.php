<?php

namespace App\Services\Catalog;

use App\Jobs\EnrichProduct;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds recipe units a product cannot be converted from yet.
 */
class ConversionGaps
{
    /**
     * Units the product's ingredient lines use and no conversion exists for.
     * Lines without a unit count pieces.
     *
     * @return Collection<int, Unit>
     */
    public function unitsWithoutConversion(Product $product): Collection
    {
        $unitIds = RecipeIngredient::where('product_id', $product->id)->distinct()->pluck('unit_id');

        return Unit::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('id', $unitIds->filter())
                ->when($unitIds->contains(null), fn (Builder $query) => $query->orWhere('key', Unit::PIECE_KEY)))
            ->whereNotIn('id', $product->conversions()->pluck('unit_id'))
            ->get();
    }

    /**
     * Queue AI suggestions for the products of a recipe that were never
     * enriched or are used with a unit they cannot be converted from.
     */
    public function enrichProductsOf(Recipe $recipe): void
    {
        Product::whereIn('id', $recipe->ingredients()->whereNotNull('product_id')->pluck('product_id'))
            ->get()
            ->filter(fn (Product $product) => $product->enriched_at === null || $this->hasGap($product))
            ->each(fn (Product $product) => EnrichProduct::dispatch($product));
    }

    private function hasGap(Product $product): bool
    {
        return $this->unitsWithoutConversion($product)
            ->contains(fn (Unit $unit) => ! $product->base_unit->isMeasuredBy($unit));
    }
}
