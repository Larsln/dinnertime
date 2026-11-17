<?php

namespace App\Models;

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchasable product that ingredient lines refer to.
 */
#[Fillable([
    'name',
    'plural_name',
    'store_section_id',
    'base_unit',
    'is_pantry_staple',
    'shelf_life_opened_days',
    'shelf_life_status',
    'status',
    'mealie_food_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_unit' => BaseUnit::class,
            'is_pantry_staple' => 'boolean',
            'shelf_life_status' => ReviewStatus::class,
            'status' => ReviewStatus::class,
        ];
    }

    /**
     * Supermarket section the product is found in.
     *
     * @return BelongsTo<StoreSection, $this>
     */
    public function storeSection(): BelongsTo
    {
        return $this->belongsTo(StoreSection::class);
    }

    /**
     * Alternative spellings and synonyms.
     *
     * @return HasMany<ProductAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    /**
     * Conversions from product specific units into the base unit.
     *
     * @return HasMany<ProductConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(ProductConversion::class);
    }

    /**
     * Package sizes the product is typically sold in.
     *
     * @return HasMany<ProductPackage, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(ProductPackage::class);
    }
}
