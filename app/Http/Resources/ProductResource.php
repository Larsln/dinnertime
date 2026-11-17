<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'name' => $this->name,
            'plural_name' => $this->plural_name,
            'base_unit' => $this->base_unit,
            'is_pantry_staple' => $this->is_pantry_staple,
            'shelf_life_opened_days' => $this->shelf_life_opened_days,
            'shelf_life_status' => $this->shelf_life_status,
            'status' => $this->status,
            'store_section' => new StoreSectionResource($this->whenLoaded('storeSection')),
            'aliases' => $this->whenLoaded('aliases', fn () => $this->aliases->pluck('name')),
            'conversions' => ProductConversionResource::collection($this->whenLoaded('conversions')),
            'packages' => ProductPackageResource::collection($this->whenLoaded('packages')),
        ];
    }
}
