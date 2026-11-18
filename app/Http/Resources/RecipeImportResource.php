<?php

namespace App\Http\Resources;

use App\Models\RecipeImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecipeImport
 */
class RecipeImportResource extends JsonResource
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
            'url' => $this->url,
            'dish_count' => $this->dish_count,
            'status' => $this->status,
            'stage' => $this->stage,
            'error' => $this->error,
            'error_message' => $this->error?->message(),
            'created_at' => $this->created_at,
            'recipe' => $this->whenLoaded('recipes', fn () => RecipeSummaryResource::make($this->recipes->first())),
            'recipes' => RecipeSummaryResource::collection($this->whenLoaded('recipes')),
        ];
    }
}
