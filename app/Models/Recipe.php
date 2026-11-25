<?php

namespace App\Models;

use App\Enums\Course;
use App\Enums\SourcePlatform;
use App\Models\Concerns\BroadcastsChanges;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recipe with its ingredient lines, steps, tags and tools.
 *
 * @property ?Course $course
 * @property ?SourcePlatform $source_platform
 */
#[Fillable([
    'title',
    'slug',
    'description',
    'course',
    'servings',
    'yield_text',
    'prep_minutes',
    'cook_minutes',
    'source_url',
    'source_platform',
    'source_video_id',
    'cover_image_path',
    'cover_thumb_path',
    'video_path',
    'meal_tags_locked',
    'mealie_recipe_id',
])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use BroadcastsChanges, HasFactory;

    public static function broadcastArea(): string
    {
        return 'recipes';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'course' => Course::class,
            'source_platform' => SourcePlatform::class,
            'meal_tags_locked' => 'boolean',
        ];
    }

    /**
     * Recipes imported from the given video, oldest first. A video that
     * shows several dishes has one recipe per dish.
     *
     * @param  Builder<Recipe>  $query
     */
    #[Scope]
    protected function fromVideo(Builder $query, SourcePlatform $platform, string $videoId): void
    {
        $query->where('source_platform', $platform)->where('source_video_id', $videoId)->orderBy('id');
    }

    /**
     * Load what the household thinks of each recipe: the average rating,
     * the number of ratings and the given user's own mark.
     *
     * @param  Builder<Recipe>  $query
     */
    #[Scope]
    protected function withMarksOf(Builder $query, User $user): void
    {
        $query
            ->withAvg('marks as average_rating', 'rating')
            ->withCount(['marks as rating_count' => fn (Builder $query) => $query->whereNotNull('rating')])
            ->with(['marks' => fn (HasMany $query) => $query->where('user_id', $user->id)]);
    }

    /**
     * Ratings and favorites of the household members.
     *
     * @return HasMany<RecipeMark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(RecipeMark::class);
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
     * Meal types and tags assigned to the recipe.
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
