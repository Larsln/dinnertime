<?php

namespace App\Services\Catalog;

use App\Enums\BaseUnit;
use App\Enums\OfferUnit;
use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use App\Models\ProductOffer;

/**
 * Turns the package size seen in a store into a package size of the
 * product, so the shopping list buys in sizes that really exist.
 */
class OfferPackages
{
    private const float EPSILON = 0.001;

    /**
     * Add the size of the offer to the product's packages, or confirm the
     * package that has it already. Sizes in a unit that does not measure
     * the product's base unit are left alone.
     */
    public function note(ProductOffer $offer): void
    {
        $amount = $this->inBaseUnit($offer);

        if ($amount === null) {
            return;
        }

        $confirmed = ['status' => ReviewStatus::Confirmed, 'source' => ValueSource::User];
        $existing = $offer->product->packages()
            ->whereBetween('amount_in_base', [$amount - self::EPSILON, $amount + self::EPSILON])
            ->first();

        if ($existing !== null) {
            $existing->update($confirmed);

            return;
        }

        $offer->product->packages()->create([
            'label' => __('messages.package', locale: config('app.content_locale')),
            'amount_in_base' => $amount,
            ...$confirmed,
        ]);
    }

    /**
     * Size of the offer in the base unit of its product, if its unit
     * measures the same thing.
     */
    private function inBaseUnit(ProductOffer $offer): ?float
    {
        if ($offer->amount === null) {
            return null;
        }

        return match ([$offer->product->base_unit, $offer->unit]) {
            [BaseUnit::Gram, OfferUnit::Gram], [BaseUnit::Milliliter, OfferUnit::Milliliter], [BaseUnit::Piece, OfferUnit::Piece] => $offer->amount,
            [BaseUnit::Gram, OfferUnit::Kilogram], [BaseUnit::Milliliter, OfferUnit::Liter] => $offer->amount * 1000,
            default => null,
        };
    }
}
