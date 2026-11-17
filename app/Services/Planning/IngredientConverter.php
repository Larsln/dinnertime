<?php

namespace App\Services\Planning;

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
use App\Enums\UnitKind;
use App\Models\Product;
use App\Models\ProductConversion;
use App\Models\RecipeIngredient;
use App\Models\Unit;

/**
 * Converts ingredient lines into the base unit of their product.
 *
 * Expects the ingredient's unit and its product's conversions with their
 * units to be loaded.
 */
class IngredientConverter
{
    private const string PIECE_KEY = 'piece';

    /**
     * Amount in the product's base unit, or null when it cannot be derived.
     */
    public function convert(RecipeIngredient $ingredient): ?ConvertedAmount
    {
        $product = $ingredient->product;
        $unit = $ingredient->unit;
        $quantity = $ingredient->quantity;

        if ($product === null || $quantity === null) {
            return null;
        }

        if ($this->measuresBaseUnit($product->base_unit, $unit)) {
            return new ConvertedAmount($quantity * ($unit->factor_to_base ?? 1), false);
        }

        $conversion = $this->findConversion($product, $unit);

        if ($conversion !== null) {
            return new ConvertedAmount(
                $quantity * $conversion->amount_in_base * $this->factorWithinDimension($conversion->unit, $unit),
                $conversion->status === ReviewStatus::Suggested,
            );
        }

        if ($this->isMassVolumeMismatch($product->base_unit, $unit)) {
            return new ConvertedAmount($quantity * $unit->factor_to_base, true);
        }

        return null;
    }

    /**
     * Whether the unit measures the same dimension as the base unit, so the
     * quantity converts by the unit's fixed factor alone.
     */
    private function measuresBaseUnit(BaseUnit $baseUnit, ?Unit $unit): bool
    {
        return match ($baseUnit) {
            BaseUnit::Gram => $unit?->kind === UnitKind::Mass,
            BaseUnit::Milliliter => $unit?->kind === UnitKind::Volume,
            BaseUnit::Piece => $unit === null || $unit->key === self::PIECE_KEY,
        };
    }

    /**
     * Find the product's conversion for the unit. A missing unit counts as
     * pieces, and mass or volume units fall back to the conversion stored
     * for another unit of the same dimension.
     */
    private function findConversion(Product $product, ?Unit $unit): ?ProductConversion
    {
        $conversions = $product->conversions;

        if ($unit === null) {
            return $conversions->first(
                fn (ProductConversion $conversion) => $conversion->unit->key === self::PIECE_KEY,
            );
        }

        return $conversions->firstWhere('unit_id', $unit->id)
            ?? $conversions->first(fn (ProductConversion $conversion) => $unit->factor_to_base !== null
                && $conversion->unit->kind === $unit->kind
                && $conversion->unit->factor_to_base !== null);
    }

    /**
     * Factor between the unit a conversion is stored for and the unit used,
     * e.g. 1000 when the conversion is per milliliter and the line uses liters.
     */
    private function factorWithinDimension(Unit $conversionUnit, ?Unit $unit): float
    {
        if ($unit === null || $unit->id === $conversionUnit->id) {
            return 1;
        }

        return $unit->factor_to_base / $conversionUnit->factor_to_base;
    }

    /**
     * Whether a mass is given for a product counted by volume or vice versa.
     * Without a density one gram is assumed to equal one milliliter.
     */
    private function isMassVolumeMismatch(BaseUnit $baseUnit, ?Unit $unit): bool
    {
        return $baseUnit !== BaseUnit::Piece && $unit?->factor_to_base !== null;
    }
}
