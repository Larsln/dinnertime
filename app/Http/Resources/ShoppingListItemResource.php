<?php

namespace App\Http\Resources;

use App\Models\ShoppingListItem;
use App\Models\Unit;
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
            'extra_amounts' => $this->extraAmounts(),
            'checked_at' => $this->checked_at,
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }

    /**
     * Needs that cannot be converted into the product's base unit, each
     * with the unit's abbreviation in the current locale. A missing unit
     * stands for pieces.
     *
     * @return list<array{quantity: float, unit_code: string}>
     */
    private function extraAmounts(): array
    {
        $units = once(fn () => Unit::all());

        return array_map(fn (array $amount) => [
            'quantity' => $amount['quantity'],
            'unit_code' => ($units->find($amount['unit_id']) ?? $units->firstWhere('key', Unit::PIECE_KEY))->displayCode(),
        ], $this->extra_amounts ?? []);
    }
}
