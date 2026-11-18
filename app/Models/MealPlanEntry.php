<?php

namespace App\Models;

use App\Enums\MealType;
use App\Models\Concerns\BroadcastsChanges;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recipe scheduled for one meal on one day.
 *
 * @property CarbonImmutable $date
 * @property MealType $meal
 */
#[Fillable(['date', 'meal', 'recipe_id', 'servings', 'cooked_at'])]
class MealPlanEntry extends Model
{
    use BroadcastsChanges;

    public static function broadcastArea(): string
    {
        return 'plan';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'meal' => MealType::class,
            'cooked_at' => 'immutable_datetime',
        ];
    }

    /**
     * Recipe that is cooked.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
