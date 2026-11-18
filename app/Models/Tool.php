<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Kitchen tool or cooking appliance from the household's catalog.
 *
 * @property bool $is_owned
 */
#[Fillable(['name', 'is_owned'])]
class Tool extends Model
{
    use BroadcastsChanges;

    public static function broadcastArea(): string
    {
        return 'tools';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_owned' => 'boolean',
        ];
    }

    /**
     * Recipes that need the tool.
     *
     * @return BelongsToMany<Recipe, $this>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }
}
