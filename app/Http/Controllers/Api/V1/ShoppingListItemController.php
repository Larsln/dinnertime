<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShoppingListItemRequest;
use App\Http\Resources\ShoppingListItemResource;
use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\Catalog\ProductResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class ShoppingListItemController extends Controller
{
    /**
     * Add a manual position: a product, or free text that is linked to the
     * product known under that name. A product that was added by hand
     * before is put back on the list instead of being listed twice.
     */
    public function store(ShoppingListItemRequest $request, ShoppingList $shoppingList, ProductResolver $products): ShoppingListItemResource
    {
        $product = $request->has('product_id')
            ? Product::findOrFail($request->validated('product_id'))
            : $products->find($request->validated('label'));

        $item = $product === null
            ? $shoppingList->items()->create(['label' => $request->validated('label'), 'is_manual' => true])
            : $shoppingList->items()->updateOrCreate(
                ['product_id' => $product->id, 'is_manual' => true],
                ['checked_at' => null, 'checked_by' => null],
            );

        return new ShoppingListItemResource($item->refresh()->load(['product.storeSection', 'product.offers.store']));
    }

    /**
     * Check a position off or on, or rename a manual one.
     */
    public function update(ShoppingListItemRequest $request, ShoppingListItem $item): ShoppingListItemResource
    {
        if ($request->has('label') && $item->is_manual) {
            $item->label = $request->validated('label');
        }

        if ($request->has('checked')) {
            $item->checked_at = $request->boolean('checked') ? CarbonImmutable::now() : null;
            $item->checked_by = $request->boolean('checked') ? $request->user()->id : null;
        }

        $item->save();

        return new ShoppingListItemResource($item->load(['product.storeSection', 'product.offers.store']));
    }

    /**
     * Remove a position.
     */
    public function destroy(ShoppingListItem $item): Response
    {
        $item->delete();

        return response()->noContent();
    }
}
