<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PantryItemRequest;
use App\Http\Resources\PantryItemResource;
use App\Models\PantryItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PantryController extends Controller
{
    /**
     * List the stock, soonest expiry first.
     */
    public function index(): AnonymousResourceCollection
    {
        $items = PantryItem::query()
            ->with('product.storeSection')
            ->orderByRaw('expires_on is null')
            ->orderBy('expires_on')
            ->orderBy('id')
            ->get();

        return PantryItemResource::collection($items);
    }

    /**
     * Add stock. Unless stated, it counts as opened today and expires after
     * the product's shelf life.
     */
    public function store(PantryItemRequest $request): PantryItemResource
    {
        $attributes = $request->validated();
        $openedOn = CarbonImmutable::parse($attributes['opened_on'] ?? today());
        $shelfLife = Product::findOrFail($attributes['product_id'])->shelf_life_opened_days;

        $item = PantryItem::create([
            'expires_on' => $shelfLife !== null ? $openedOn->addDays($shelfLife) : null,
            ...$attributes,
            'opened_on' => $openedOn,
        ]);

        return new PantryItemResource($item->load('product.storeSection'));
    }

    /**
     * Change an item.
     */
    public function update(PantryItemRequest $request, PantryItem $item): PantryItemResource
    {
        $item->update($request->validated());

        return new PantryItemResource($item->load('product.storeSection'));
    }

    /**
     * Remove an item.
     */
    public function destroy(PantryItem $item): Response
    {
        $item->delete();

        return response()->noContent();
    }
}
