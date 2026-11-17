<?php

namespace App\Services\Planning;

use App\Models\Product;
use Carbon\CarbonImmutable;

/**
 * Simulates the stock of one product over a period.
 *
 * Demands are served from opened stock first. When stock runs out, packages
 * are bought for everything needed before a newly opened package would
 * expire, so perishable products are not bought for uses they cannot reach.
 */
class ProductLedger
{
    private const float EPSILON = 1e-6;

    public function __construct(private readonly PackagePicker $picker) {}

    /**
     * @param  list<Demand>  $demands  Ordered by date.
     * @param  list<StockLot>  $lots  Stock on hand at the start.
     */
    public function forecast(
        Product $product,
        array $demands,
        array $lots,
        bool $approximate,
        bool $hasUnquantifiedUse,
    ): ProductForecast {
        $purchased = 0.0;
        $wasted = 0.0;
        $packageCounts = [];

        foreach ($demands as $index => $demand) {
            $wasted += $this->discardExpired($lots, $demand->date);
            $open = $this->consume($lots, $demand->amount);

            if ($open <= self::EPSILON) {
                continue;
            }

            $expiresOn = $this->expiryWhenOpenedOn($product, $demand->date);
            $need = $open + $this->upcomingNeed($demands, $index, $expiresOn);
            $selection = $this->picker->pick($need, $product->packages);

            $purchased += $selection->amount;
            $lots[] = new StockLot($selection->amount - $open, $demand->date, $expiresOn);

            foreach ($selection->counts as $packageId => $count) {
                $packageCounts[$packageId] = ($packageCounts[$packageId] ?? 0) + $count;
            }
        }

        $remainingLots = array_values(array_filter($lots, fn (StockLot $lot) => $lot->amount > self::EPSILON));

        return new ProductForecast(
            product: $product,
            needed: array_sum(array_map(fn (Demand $demand) => $demand->amount, $demands)),
            purchased: $purchased,
            packageCounts: $packageCounts,
            leftover: array_sum(array_map(fn (StockLot $lot) => $lot->amount, $remainingLots)),
            wasted: $wasted,
            uses: $demands,
            remainingLots: $remainingLots,
            approximate: $approximate,
            hasUnquantifiedUse: $hasUnquantifiedUse,
        );
    }

    /**
     * Remove lots that expired before the date and return their total amount.
     *
     * @param  list<StockLot>  $lots
     */
    private function discardExpired(array &$lots, CarbonImmutable $date): float
    {
        $expired = array_filter($lots, fn (StockLot $lot) => $lot->isExpiredOn($date));
        $lots = array_values(array_filter($lots, fn (StockLot $lot) => ! $lot->isExpiredOn($date)));

        return array_sum(array_map(fn (StockLot $lot) => $lot->amount, $expired));
    }

    /**
     * Take the amount from the lots, soonest expiry first, and return what
     * could not be served.
     *
     * @param  list<StockLot>  $lots
     */
    private function consume(array &$lots, float $amount): float
    {
        usort($lots, fn (StockLot $a, StockLot $b) => [$a->expiresOn === null, $a->expiresOn] <=> [$b->expiresOn === null, $b->expiresOn]);

        foreach ($lots as $lot) {
            $taken = min($lot->amount, $amount);
            $lot->amount -= $taken;
            $amount -= $taken;
        }

        return $amount;
    }

    private function expiryWhenOpenedOn(Product $product, CarbonImmutable $date): ?CarbonImmutable
    {
        return $product->shelf_life_opened_days !== null
            ? $date->addDays($product->shelf_life_opened_days)
            : null;
    }

    /**
     * Sum of the later demands a package opened now can still serve.
     *
     * @param  list<Demand>  $demands
     */
    private function upcomingNeed(array $demands, int $currentIndex, ?CarbonImmutable $expiresOn): float
    {
        $need = 0.0;

        foreach (array_slice($demands, $currentIndex + 1) as $demand) {
            if ($expiresOn === null || $demand->date->lte($expiresOn)) {
                $need += $demand->amount;
            }
        }

        return $need;
    }
}
