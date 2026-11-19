<?php

namespace App\Services\Planning;

/**
 * Size the shopping list may buy a product in.
 */
final readonly class PackageSize
{
    /**
     * @param  int  $id  Id of the offer the size comes from.
     * @param  string  $label  Name of the store that sells it, empty for sizes sold everywhere.
     * @param  float  $amount  Content in the base unit of the product.
     */
    public function __construct(
        public int $id,
        public string $label,
        public float $amount,
    ) {}
}
