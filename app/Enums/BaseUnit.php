<?php

namespace App\Enums;

use App\Models\Unit;

/**
 * Unit in which all amounts of a product are stored and summed up.
 */
enum BaseUnit: string
{
    case Gram = 'g';
    case Milliliter = 'ml';
    case Piece = 'piece';

    /**
     * Whether a recipe unit measures this base unit directly, so quantities
     * convert by the unit's fixed factor without a product conversion.
     * A missing unit counts pieces.
     */
    public function isMeasuredBy(?Unit $unit): bool
    {
        return match ($this) {
            self::Gram => $unit?->kind === UnitKind::Mass,
            self::Milliliter => $unit?->kind === UnitKind::Volume,
            self::Piece => $unit === null || $unit->key === Unit::PIECE_KEY,
        };
    }
}
