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
            'extra_amounts' => $this->extraAmounts($this->extra_amounts ?? []),
            'variants' => array_map(fn (array $variant) => [
                ...$variant,
                'packages' => $variant['packages'] ?? [],
                'uses' => $variant['uses'] ?? [],
                'extra_amounts' => $this->extraAmounts($variant['extra_amounts'] ?? []),
            ], $this->variants ?? []),
            'checked_at' => $this->checked_at,
            'skipped_at' => $this->skipped_at,
            'substitute' => $this->isSubstituted() ? [
                'product_id' => $this->substitute_product_id,
                'name' => $this->substituteProduct->name ?? $this->substitute_label,
            ] : null,
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }

    /**
     * Needs that cannot be converted into the product's base unit, each
     * with the unit's abbreviation in the current locale. A missing unit
     * stands for pieces.
     *
     * @param  list<array{unit_id: ?int, quantity: float}>  $amounts
     * @return list<array{quantity: float, unit_code: string}>
     */
    private function extraAmounts(array $amounts): array
    {
        $units = once(fn () => Unit::all());

        return array_map(fn (array $amount) => [
            'quantity' => $amount['quantity'],
            'unit_code' => ($units->find($amount['unit_id']) ?? $units->firstWhere('key', Unit::PIECE_KEY))->displayCode(),
        ], $amounts);
    }
}
