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
     * @param  list<StapleNeed>  $staples  Needed products that are permanently in stock and never bought for a meal.
     */
    public function __construct(
        public array $products,
        public array $unresolved,
        public array $staples = [],
    ) {}
}
