<?php

namespace App\Http\Resources;

use App\Models\PantryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PantryItem
 */
class PantryItemResource extends JsonResource
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
            'amount' => $this->amount,
            'opened_on' => $this->opened_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
