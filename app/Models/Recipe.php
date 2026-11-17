<?php

namespace App\Models;

use App\Enums\SourcePlatform;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recipe with its ingredient lines, steps, tags and tools.
 *
 * @property ?SourcePlatform $source_platform
 */
#[Fillable([
    'title',
    'slug',
    'description',
    'servings',
    'yield_text',
    'prep_minutes',
    'cook_minutes',
    'source_url',
    'source_platform',
    'source_video_id',
    'cover_image_path',
    'meal_tags_locked',
    'mealie_recipe_id',
])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_platform' => SourcePlatform::class,
            'meal_tags_locked' => 'boolean',
        ];
    }

    /**
     * Ingredient lines in display order.
     *
     * @return HasMany<RecipeIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('position');
    }

    /**
     * Preparation steps in display order.
     *
     * @return HasMany<RecipeStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('position');
    }

    /**
     * Meal types, categories and tags assigned to the recipe.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('type')->orderBy('position');
    }

    /**
     * Kitchen tools needed for the recipe.
     *
     * @return BelongsToMany<Tool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class)->orderBy('name');
    }
}
