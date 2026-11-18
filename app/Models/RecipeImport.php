<?php

namespace App\Models;

use App\Enums\ImportError;
use App\Enums\ImportStage;
use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Request to turn a shared link into one recipe per dish it shows.
 *
 * @property ImportStatus $status
 * @property ?ImportStage $stage
 * @property ?ImportError $error
 */
#[Fillable(['url', 'dish_count', 'status', 'stage', 'error', 'user_id'])]
class RecipeImport extends Model
{
    public const int MAX_DISHES = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'stage' => ImportStage::class,
            'error' => ImportError::class,
        ];
    }

    /**
     * Whether the import is waiting or running, as opposed to completed
     * or failed.
     */
    public function isRunning(): bool
    {
        return in_array($this->status, [ImportStatus::Pending, ImportStatus::Processing], true);
    }

    /**
     * Recipes the import produced or found to exist already, one per dish
     * in the order they appear in the source.
     *
     * @return BelongsToMany<Recipe, $this>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class)->orderByPivot('position');
    }

    /**
     * Replace the recipes of the import, keeping the given order.
     *
     * @param  iterable<Recipe>  $recipes
     */
    public function attachRecipes(iterable $recipes): void
    {
        $positions = [];

        foreach ($recipes as $recipe) {
            $positions[$recipe->id] = ['position' => count($positions)];
        }

        $this->recipes()->sync($positions);
    }
}
