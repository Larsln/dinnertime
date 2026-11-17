<?php

namespace App\Enums;

/**
 * Describes how a unit converts into a product's base unit.
 *
 * Mass and volume units convert by a fixed factor, product specific units
 * such as a tablespoon or a bunch need a conversion stored on the product.
 */
enum UnitKind: string
{
    case Mass = 'mass';
    case Volume = 'volume';
    case ProductSpecific = 'product_specific';
}
