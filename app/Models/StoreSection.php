<?php

namespace App\Models;

use Database\Factories\StoreSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Area of the supermarket, ordered by the route taken through the store.
 *
 * Built-in sections carry a key and are named by translation, sections
 * added by the household carry their own name.
 */
#[Fillable(['key', 'name', 'position'])]
class StoreSection extends Model
{
    /** @use HasFactory<StoreSectionFactory> */
    use HasFactory;

    /**
     * Name in the current locale.
     */
    public function displayName(): string
    {
        return $this->name ?? __("reference.store_sections.{$this->key}");
    }

    /**
     * Products shelved in this section.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
