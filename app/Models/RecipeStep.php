<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single preparation step of a recipe.
 */
#[Fillable(['recipe_id', 'position', 'title', 'text'])]
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
