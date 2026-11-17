<?php

namespace App\Services\Planning;

/**
 * Purchases and leftovers resulting from a set of planned meals.
 */
final readonly class ShoppingForecast
{
    /**
     * @param  list<ProductForecast>  $products
     * @param  list<string>  $unresolved  Ingredient texts that refer to no product.
     */
    public function __construct(
        public array $products,
        public array $unresolved,
    ) {}
}
