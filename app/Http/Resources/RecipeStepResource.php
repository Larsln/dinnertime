<?php

namespace App\Http\Resources;

use App\Models\RecipeStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecipeStep
 */
class RecipeStepResource extends JsonResource
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
            'title' => $this->title,
            'text' => $this->text,
        ];
    }
}
