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

    /**
     * Units that measure what a product is bought by, smallest first.
     *
     * @return list<self>
     */
    public static function measuring(BaseUnit $baseUnit): array
    {
        return match ($baseUnit) {
            BaseUnit::Gram => [self::Gram, self::Kilogram],
            BaseUnit::Milliliter => [self::Milliliter, self::Liter],
            BaseUnit::Piece => [self::Piece],
        };
    }
}
