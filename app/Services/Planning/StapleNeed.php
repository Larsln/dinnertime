<?php

namespace App\Services\Planning;

use App\Models\Product;

/**
 * Product planned meals need that is permanently in stock, with the meals
 * that use it.
 */
final readonly class StapleNeed
{
    /**
     * @param  list<Demand>  $uses  Meals using the product. Their amounts are not tracked.
     */
    public function __construct(
        public Product $product,
        public array $uses,
    ) {}
}
