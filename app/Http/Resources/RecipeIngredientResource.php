<?php

namespace App\Http\Resources;

use App\Models\RecipeIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecipeIngredient
 */
class RecipeIngredientResource extends JsonResource
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
            'position' => $this->position,
            'section' => $this->section,
            'quantity' => $this->quantity,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'note' => $this->note,
            'is_estimated' => $this->is_estimated,
            'raw_text' => $this->raw_text,
        ];
    }
}
