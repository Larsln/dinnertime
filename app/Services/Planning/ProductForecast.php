<?php

namespace App\Services\Planning;

use App\Models\Product;

/**
 * What has to be bought of a product for a period and what remains of it.
 */
final readonly class ProductForecast
{
    /**
     * @param  float  $needed  Amount the planned recipes consume.
     * @param  float  $purchased  Amount contained in the packages to buy.
     * @param  array<int, int>  $packageCounts  Number of packages keyed by package id.
     * @param  float  $leftover  Usable amount remaining after the last meal.
     * @param  float  $wasted  Amount that expires before a recipe uses it.
     * @param  list<Demand>  $uses
     * @param  list<StockLot>  $remainingLots
     * @param  bool  $approximate  Whether amounts rely on unconfirmed conversions.
     * @param  bool  $hasUnquantifiedUse  Whether a recipe needs the product without a usable amount.
     * @param  list<UnconvertedAmount>  $unconverted  Needs in recipe units that cannot be converted, bought in addition.
     */
    public function __construct(
        public Product $product,
        public float $needed,
        public float $purchased,
        public array $packageCounts,
        public float $leftover,
        public float $wasted,
        public array $uses,
        public array $remainingLots,
        public bool $approximate,
        public bool $hasUnquantifiedUse,
        public array $unconverted = [],
    ) {}

    /**
     * Whether the product has to appear on the shopping list.
     */
    public function requiresPurchase(): bool
    {
        return $this->purchased > 0
            || $this->unconverted !== []
            || ($this->hasUnquantifiedUse && $this->remainingLots === []);
    }
}
