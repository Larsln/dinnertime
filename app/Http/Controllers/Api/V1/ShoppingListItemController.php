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
    private const array RELATIONS = ['product.storeSection', 'product.offers.store', 'substituteProduct'];

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
                ['checked_at' => null, 'checked_by' => null, 'skipped_at' => null, 'substitute_product_id' => null, 'substitute_label' => null],
            );

        return new ShoppingListItemResource($item->refresh()->load(self::RELATIONS));
    }

    /**
     * Settle a position or open it again, or rename a manual one. Checking
     * it off states it was bought, with a substitute that something else
     * was bought in its place, and skipping it that it is not bought at
     * all. A position that is opened again forgets its substitute.
     */
    public function update(ShoppingListItemRequest $request, ShoppingListItem $item, ProductResolver $products): ShoppingListItemResource
    {
        if ($request->has('label') && $item->is_manual) {
            $item->label = $request->validated('label');
        }

        if ($request->has('checked')) {
            $item->checked_at = $request->boolean('checked') ? CarbonImmutable::now() : null;
            $item->checked_by = $request->boolean('checked') ? $request->user()->id : null;
        }

        if ($request->has('skipped')) {
            $item->skipped_at = $request->boolean('skipped') ? CarbonImmutable::now() : null;
        }

        if ($request->hasAny(['substitute_product_id', 'substitute_label'])) {
            $label = $request->validated('substitute_label');
            $item->substitute_product_id = $request->validated('substitute_product_id') ?? (filled($label) ? $products->find($label)?->id : null);
            $item->substitute_label = $item->substitute_product_id === null && filled($label) ? $label : null;
        }

        if ($item->checked_at === null) {
            $item->substitute_product_id = null;
            $item->substitute_label = null;
        }

        $item->save();

        return new ShoppingListItemResource($item->load(self::RELATIONS));
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
