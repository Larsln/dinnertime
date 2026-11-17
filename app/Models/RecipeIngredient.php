<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single ingredient line of a recipe.
 *
 * @property ?float $quantity
 */
#[Fillable([
    'recipe_id',
    'position',
    'section',
    'product_id',
    'quantity',
    'unit_id',
    'note',
    'is_estimated',
    'raw_text',
])]
class RecipeIngredient extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'is_estimated' => 'boolean',
        ];
    }

    /**
     * Recipe the line belongs to.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Product the line refers to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Unit the quantity is given in, empty for countable products.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
