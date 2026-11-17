<?php

namespace App\Enums;

/**
 * Unit in which all amounts of a product are stored and summed up.
 */
enum BaseUnit: string
{
    case Gram = 'g';
    case Milliliter = 'ml';
    case Piece = 'piece';
}
