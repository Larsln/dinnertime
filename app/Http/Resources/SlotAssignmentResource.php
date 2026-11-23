<?php

namespace App\Http\Resources;

use App\Services\Planning\SlotAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SlotAssignment
 */
class SlotAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->slot->date->toDateString(),
            'meal' => $this->slot->meal,
            'recipe' => new RecipeSummaryResource($this->recipe),
            'kept' => $this->kept,
            'reasons' => $this->reasons,
        ];
    }
}
