<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PeriodRequest;
use App\Http\Resources\ShoppingListResource;
use App\Models\ShoppingList;
use App\Services\Planning\LeftoverStocker;
use App\Services\Planning\ShoppingListSynchronizer;

class ShoppingListController extends Controller
{
    private const array ITEM_RELATIONS = ['items.product.storeSection', 'items.product.offers'];

    /**
     * Show the list of the latest period.
     */
    public function current(): ShoppingListResource
    {
        return new ShoppingListResource(
            ShoppingList::with(self::ITEM_RELATIONS)->orderByDesc('starts_on')->firstOrFail(),
        );
    }

    /**
     * Show a list.
     */
    public function show(ShoppingList $shoppingList): ShoppingListResource
    {
        return new ShoppingListResource($shoppingList->load(self::ITEM_RELATIONS));
    }

    /**
     * Create the list of a period from the meal plan, or refresh it.
     */
    public function store(PeriodRequest $request, ShoppingListSynchronizer $synchronizer): ShoppingListResource
    {
        $list = $synchronizer->sync($request->from(), $request->to());

        return new ShoppingListResource($list->load(self::ITEM_RELATIONS));
    }

    /**
     * Move what remains after the period into the pantry. Allowed once.
     */
    public function stockLeftovers(ShoppingList $shoppingList, LeftoverStocker $stocker): ShoppingListResource
    {
        abort_if($shoppingList->leftovers_stocked_at !== null, 409, __('messages.leftovers_already_stocked'));

        $stocker->stock($shoppingList);

        return new ShoppingListResource($shoppingList->load(self::ITEM_RELATIONS));
    }
}
