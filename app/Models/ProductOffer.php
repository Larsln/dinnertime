<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a product can be bought: the store, the size of the package it
 * sells in the product's base unit and what that package costs.
 *
 * @property ?float $amount_in_base
 * @property ?float $price
 */
#[Fillable(['product_id', 'store_id', 'amount_in_base', 'price'])]
class ProductOffer extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_in_base' => 'float',
            'price' => 'float',
        ];
    }

    /**
     * Store that sells the product.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Product the store sells.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
