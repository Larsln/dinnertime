<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Recipe
 */
class RecipeSummaryResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'servings' => $this->servings,
            'yield_text' => $this->yield_text,
            'prep_minutes' => $this->prep_minutes,
            'cook_minutes' => $this->cook_minutes,
            'cover_url' => $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
