<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recipe offered in a dinner round, with the number of ingredients the
 * household would have to buy for it.
 */
#[Fillable(['dinner_round_id', 'recipe_id', 'position', 'missing_count'])]
class DinnerRoundCard extends Model
{
    public $timestamps = false;

    /**
     * Recipe on the card.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
