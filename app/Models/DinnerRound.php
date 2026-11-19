<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A round in which the household decides what to cook tonight: everyone
 * says yes or no to the same recipes, and the first recipe enough members
 * like is the match.
 *
 * @property CarbonImmutable $date
 * @property bool $is_solo
 * @property ?CarbonImmutable $closed_at
 */
#[Fillable(['date', 'is_solo', 'started_by', 'matched_recipe_id', 'closed_at'])]
class DinnerRound extends Model
{
    use BroadcastsChanges;

    /**
     * Number of members who have to like a recipe when they decide together.
     */
    public const int LIKES_TO_MATCH = 2;

    public static function broadcastArea(): string
    {
        return 'dinner';
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
            'is_solo' => 'boolean',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Rounds of the given day that are still being decided or have a match
     * nobody acted on yet.
     *
     * @param  Builder<DinnerRound>  $query
     */
    #[Scope]
    protected function openOn(Builder $query, CarbonImmutable $date): void
    {
        $query->whereDate('date', $date)->whereNull('closed_at');
    }

    /**
     * Likes a recipe needs to become the match.
     */
    public function likesToMatch(): int
    {
        return $this->is_solo ? 1 : self::LIKES_TO_MATCH;
    }

    /**
     * Recipes to decide on, in the order everyone sees them.
     *
     * @return HasMany<DinnerRoundCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(DinnerRoundCard::class)->orderBy('position');
    }

    /**
     * What the members said to the recipes.
     *
     * @return HasMany<DinnerRoundVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(DinnerRoundVote::class);
    }

    /**
     * Recipe the household agreed on.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'matched_recipe_id');
    }
}
