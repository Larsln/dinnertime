<?php

namespace App\Services\Planning;

/**
 * Rates how much food a plan leaves over, measured in packages.
 *
 * Expired food counts in full. Leftovers count the more, the sooner the
 * product spoils once opened.
 */
class PlanCost
{
    /**
     * Shelf life in days at or below which a leftover counts like waste.
     */
    private const int PERISHABLE_DAYS = 3;

    /**
     * Weight of leftovers that keep for a long time or indefinitely.
     */
    private const float MINIMUM_WEIGHT = 0.1;

    public function of(ShoppingForecast $forecast): float
    {
        return array_sum(array_map($this->ofProduct(...), $forecast->products));
    }

    private function ofProduct(ProductForecast $forecast): float
    {
        $surplus = $forecast->wasted + $forecast->leftover * $this->leftoverWeight($forecast);

        if ($surplus <= 0) {
            return 0;
        }

        $smallestPackage = $forecast->product->buyableSizes()->min('amount_in_base');

        return $surplus / ($smallestPackage ?? max($forecast->purchased, 1));
    }

    private function leftoverWeight(ProductForecast $forecast): float
    {
        $shelfLife = $forecast->product->shelf_life_opened_days;

        return $shelfLife === null
            ? self::MINIMUM_WEIGHT
            : max(self::MINIMUM_WEIGHT, min(1, self::PERISHABLE_DAYS / max($shelfLife, 1)));
    }
}
