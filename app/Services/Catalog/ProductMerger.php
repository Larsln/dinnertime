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
 * and packages are discarded.
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

            $duplicate->delete();
            ProductAlias::firstOrCreate(['name' => $duplicate->name], ['product_id' => $target->id]);
        });
    }
}
