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
            'status' => $this->status,
            'stage' => $this->stage,
            'error' => $this->error,
            'error_message' => $this->error?->message(),
            'created_at' => $this->created_at,
            'recipe' => new RecipeSummaryResource($this->whenLoaded('recipe')),
        ];
    }
}
