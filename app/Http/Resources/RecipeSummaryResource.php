<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'course' => $this->course,
            'course_name' => $this->course?->label(),
            'side_kind' => $this->side_kind,
            'side_options' => $this->side_options ?? [],
            'servings' => $this->servings,
            'yield_text' => $this->yield_text,
            'prep_minutes' => $this->prep_minutes,
            'cook_minutes' => $this->cook_minutes,
            'cover_url' => $this->cover_image_path ? asset("storage/{$this->cover_image_path}") : null,
            'cover_thumb_url' => $this->cover_thumb_path ? asset("storage/{$this->cover_thumb_path}") : null,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'rating' => $this->whenLoaded('marks', fn () => $this->marks->first()?->rating),
            'is_favorite' => $this->whenLoaded('marks', fn () => (bool) $this->marks->first()?->is_favorite),
            'average_rating' => $this->whenHas('average_rating', fn (mixed $average) => $average === null ? null : round((float) $average, 1)),
            'rating_count' => $this->whenHas('rating_count'),
        ];
    }
}
