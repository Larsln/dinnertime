<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single preparation step of a recipe. Consecutive steps with the same
 * section form a group such as "Boden" or "Füllung".
 */
#[Fillable(['recipe_id', 'position', 'section', 'text'])]
class RecipeStep extends Model
{
    /**
     * Recipe the step belongs to.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
