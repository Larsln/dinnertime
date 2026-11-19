<?php

namespace App\Http\Resources;

use App\Models\ProductOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductOffer
 */
class ProductOfferResource extends JsonResource
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
            'store_id' => $this->store_id,
            'store' => $this->store->name,
            'amount' => $this->amount,
            'unit' => $this->unit,
            'price' => $this->price,
        ];
    }
}
