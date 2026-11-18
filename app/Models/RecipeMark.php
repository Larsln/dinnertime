<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one household member thinks of a recipe: a rating from 0 (bad) to
 * 10 (good) and whether it is a favorite.
 *
 * @property ?int $rating
 * @property bool $is_favorite
 */
#[Fillable(['recipe_id', 'user_id', 'rating', 'is_favorite'])]
class RecipeMark extends Model
{
    use BroadcastsChanges;

    public static function broadcastArea(): string
    {
        return 'recipes';
    }

    public const int MIN_RATING = 0;

    public const int MAX_RATING = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_favorite' => 'boolean',
        ];
    }

    /**
     * Recipe the mark belongs to.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
