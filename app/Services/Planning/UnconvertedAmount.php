<?php

namespace App\Services\Planning;

/**
 * Quantity of a product in a recipe unit that cannot be converted into the
 * product's base unit, for example pieces of a fruit bought by weight.
 */
final readonly class UnconvertedAmount
{
    /**
     * @param  ?int  $unitId  Unit of the quantity, null for pieces.
     */
    public function __construct(
        public ?int $unitId,
        public float $quantity,
    ) {}
}
