<?php

namespace App\Http\Resources;

use App\Models\MealPlanEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MealPlanEntry
 */
class MealPlanEntryResource extends JsonResource
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
            'date' => $this->date->toDateString(),
            'meal' => $this->meal,
            'servings' => $this->servings,
            'cooked_at' => $this->cooked_at,
            'recipe' => new RecipeSummaryResource($this->whenLoaded('recipe')),
        ];
    }
}
