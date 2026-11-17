<?php

namespace App\Services\Planning;

/**
 * Ingredient quantity expressed in the base unit of its product.
 */
final readonly class ConvertedAmount
{
    /**
     * @param  bool  $approximate  Whether the amount relies on an unconfirmed or assumed conversion.
     */
    public function __construct(
        public float $amount,
        public bool $approximate,
    ) {}
}
