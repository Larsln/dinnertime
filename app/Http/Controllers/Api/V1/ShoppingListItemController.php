<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShoppingListItemRequest;
use App\Http\Resources\ShoppingListItemResource;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class ShoppingListItemController extends Controller
{
    /**
     * Add a manual position.
     */
    public function store(ShoppingListItemRequest $request, ShoppingList $shoppingList): ShoppingListItemResource
    {
        $item = $shoppingList->items()->create([
            'label' => $request->validated('label'),
            'is_manual' => true,
        ]);

        return new ShoppingListItemResource($item->refresh());
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

        return new ShoppingListItemResource($item->load('product.storeSection'));
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
