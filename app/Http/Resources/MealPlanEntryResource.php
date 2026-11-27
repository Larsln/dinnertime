<?php

namespace App\Http\Resources;

use App\Models\MealPlanEntry;
use App\Models\PortionFeedback;
use App\Models\User;
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
        $names = once(fn () => User::pluck('name', 'id'));

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'date' => $this->date->toDateString(),
            'meal' => $this->meal,
            'servings' => $this->servings,
            'portions' => $this->portions ?? $this->servings,
            'guests' => $this->guests,
            'eaters' => $this->eaters === null ? null : array_map(fn (array $eater) => [
                ...$eater,
                'name' => $names[$eater['user_id']] ?? null,
            ], $this->eaters),
            'portion_feedback' => $this->whenLoaded('feedback', fn () => $this->feedback->map(fn (PortionFeedback $feedback) => [
                'user_id' => $feedback->user_id,
                'verdict' => $feedback->verdict,
            ])->values()),
            'cooked_at' => $this->cooked_at,
            'recipe' => new RecipeSummaryResource($this->whenLoaded('recipe')),
        ];
    }
}
