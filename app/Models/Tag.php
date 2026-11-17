<?php

namespace App\Models;

use App\Enums\TagType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Entry of one of the fixed recipe vocabularies, named by translation.
 *
 * @property TagType $type
 */
#[Fillable(['type', 'key', 'position'])]
class Tag extends Model
{
    /**
     * Name in the current locale.
     */
    public function displayName(): string
    {
        return __("reference.tags.{$this->type->value}.{$this->key}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TagType::class,
        ];
    }

    /**
     * Recipes labelled with this tag.
     *
     * @return BelongsToMany<Recipe, $this>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }
}
