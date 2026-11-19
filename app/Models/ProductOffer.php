<?php

namespace App\Models;

use App\Enums\BaseUnit;
use App\Enums\OfferUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A package a product is sold in: its size as the shelf label states it,
 * what it costs and the store that sells it. Without a store the size is
 * sold everywhere.
 *
 * @property ?float $amount
 * @property ?OfferUnit $unit
 * @property ?float $price
 */
#[Fillable(['product_id', 'store_id', 'amount', 'unit', 'price'])]
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
            'amount' => 'float',
            'unit' => OfferUnit::class,
            'price' => 'float',
        ];
    }

    /**
     * Size of the package in the base unit of the product, if the unit
     * of the shelf label measures the same thing.
     */
    public function amountInBase(): ?float
    {
        if ($this->amount === null) {
            return null;
        }

        return match ([$this->product->base_unit, $this->unit]) {
            [BaseUnit::Gram, OfferUnit::Gram], [BaseUnit::Milliliter, OfferUnit::Milliliter], [BaseUnit::Piece, OfferUnit::Piece] => $this->amount,
            [BaseUnit::Gram, OfferUnit::Kilogram], [BaseUnit::Milliliter, OfferUnit::Liter] => $this->amount * 1000,
            default => null,
        };
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
