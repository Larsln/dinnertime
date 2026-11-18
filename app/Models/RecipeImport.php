<?php

namespace App\Models;

use App\Enums\ImportError;
use App\Enums\ImportStage;
use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Request to turn a shared link into a recipe.
 *
 * @property ImportStatus $status
 * @property ?ImportStage $stage
 * @property ?ImportError $error
 */
#[Fillable(['url', 'status', 'stage', 'error', 'recipe_id', 'user_id'])]
class RecipeImport extends Model
{
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
     * Recipe the import produced or found to exist already.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
