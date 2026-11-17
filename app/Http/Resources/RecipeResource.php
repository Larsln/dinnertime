<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Full recipe, extending the summary with everything needed to cook it.
 */
class RecipeResource extends RecipeSummaryResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'source_url' => $this->source_url,
            'source_platform' => $this->source_platform,
            'meal_tags_locked' => $this->meal_tags_locked,
            'ingredients' => RecipeIngredientResource::collection($this->whenLoaded('ingredients')),
            'steps' => RecipeStepResource::collection($this->whenLoaded('steps')),
            'tools' => ToolResource::collection($this->whenLoaded('tools')),
        ];
    }
}
