<?php

namespace App\Http\Resources;

use App\Models\ProductConversion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductConversion
 */
class ProductConversionResource extends JsonResource
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
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'amount_in_base' => $this->amount_in_base,
            'status' => $this->status,
            'source' => $this->source,
        ];
    }
}
