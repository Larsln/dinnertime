<?php

namespace App\Enums;

/**
 * Unit a shelf label states a package size in.
 */
enum OfferUnit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';
    case Piece = 'piece';
}
