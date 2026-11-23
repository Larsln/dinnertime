<?php

namespace App\Services\Catalog;

use App\Models\PantryItem;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\RecipeIngredient;
use App\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

/**
 * Merges a duplicate product into another one.
 *
 * Everything that refers to the duplicate is moved, and its name and
 * aliases become aliases of the remaining product. Its own conversions
 * and packages are discarded. Products bought as the duplicate are bought
 * as the remaining product, or as what that one is bought as.
 */
class ProductMerger
{
    public function merge(Product $duplicate, Product $target): void
    {
        DB::transaction(function () use ($duplicate, $target) {
            RecipeIngredient::where('product_id', $duplicate->id)->update(['product_id' => $target->id]);
            PantryItem::where('product_id', $duplicate->id)->update(['product_id' => $target->id]);
            ShoppingListItem::where('product_id', $duplicate->id)->update(['product_id' => $target->id]);
            ProductAlias::where('product_id', $duplicate->id)->update(['product_id' => $target->id]);
            $root = $target->buy_as_id === null || $target->buy_as_id === $duplicate->id ? $target->id : $target->buy_as_id;
            $duplicate->variants->each(fn (Product $variant) => $variant->update(['buy_as_id' => $variant->id === $root ? null : $root]));

            $duplicate->delete();
            ProductAlias::firstOrCreate(['name' => $duplicate->name], ['product_id' => $target->id]);
        });
    }
}
