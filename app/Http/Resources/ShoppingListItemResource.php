<?php

namespace App\Http\Resources;

use App\Models\ShoppingListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShoppingListItem
 */
class ShoppingListItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'is_manual' => $this->is_manual,
            'needed_amount' => $this->needed_amount,
            'purchase_amount' => $this->purchase_amount,
            'packages' => $this->packages ?? [],
            'leftover_amount' => $this->leftover_amount,
            'wasted_amount' => $this->wasted_amount,
            'uses' => $this->uses ?? [],
            'is_approximate' => $this->is_approximate,
            'checked_at' => $this->checked_at,
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
