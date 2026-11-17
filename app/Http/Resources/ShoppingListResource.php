<?php

namespace App\Http\Resources;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShoppingList
 */
class ShoppingListResource extends JsonResource
{
    private const int LAST_POSITION = PHP_INT_MAX;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'leftovers_stocked_at' => $this->leftovers_stocked_at,
            'items' => ShoppingListItemResource::collection($this->itemsInStoreOrder()),
        ];
    }

    /**
     * Positions ordered by the route through the store. Products without a
     * section follow, free-text positions come last.
     *
     * @return list<ShoppingListItem>
     */
    private function itemsInStoreOrder(): array
    {
        return $this->items
            ->sortBy(fn (ShoppingListItem $item) => [
                $item->product?->storeSection->position ?? self::LAST_POSITION,
                $item->product === null,
                mb_strtolower($item->product->name ?? $item->label),
            ])
            ->values()
            ->all();
    }
}
