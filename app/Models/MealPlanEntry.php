<?php

namespace App\Models;

use App\Enums\MealType;
use App\Models\Concerns\BroadcastsChanges;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recipe scheduled for one meal on one day.
 *
 * `servings` counts the people who eat. `eaters` names the household
 * members among them with the share of a standard portion each gets, and
 * `guests` the others, who get a standard portion. `portions` is what is
 * cooked and shopped for, the servings while it is not set.
 *
 * @property CarbonImmutable $date
 * @property MealType $meal
 * @property ?list<array{user_id: int, factor: float}> $eaters
 * @property ?float $portions
 */
#[Fillable(['date', 'meal', 'recipe_id', 'servings', 'cooked_at', 'eaters', 'guests', 'portions'])]
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
            'eaters' => 'array',
            'portions' => 'float',
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

    /**
     * What those who ate said about their portion.
     *
     * @return HasMany<PortionFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(PortionFeedback::class);
    }

    /**
     * Share of a standard portion cooked for a member. Whoever is not
     * named among the eaters counts as a guest.
     */
    public function factorOf(int $userId): float
    {
        return (float) (collect($this->eaters)->firstWhere('user_id', $userId)['factor'] ?? 1);
    }
}
