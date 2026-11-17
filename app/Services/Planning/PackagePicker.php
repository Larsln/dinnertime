<?php

namespace App\Services\Planning;

use App\Models\ProductPackage;
use Illuminate\Support\Collection;

/**
 * Chooses the combination of package sizes that covers a needed amount with
 * the least surplus.
 */
class PackagePicker
{
    /**
     * Share of the needed amount each additional package costs. Keeps the
     * picker from preferring many small packages over one that fits nearly
     * as well.
     */
    private const float PACKAGE_PENALTY = 0.05;

    private const float EPSILON = 1e-6;

    /**
     * Pick packages covering the need. Without known package sizes the
     * exact amount is bought.
     *
     * @param  Collection<int, ProductPackage>  $packages
     */
    public function pick(float $need, Collection $packages): PackageSelection
    {
        if ($packages->isEmpty() || $need <= self::EPSILON) {
            return new PackageSelection([], max($need, 0));
        }

        $sizes = $packages->sortByDesc('amount_in_base')->values();
        $best = ['cost' => INF, 'counts' => []];

        $this->search($need, $sizes, 0, [], 0, $best);

        $counts = [];
        $amount = 0;

        foreach ($best['counts'] as $index => $count) {
            if ($count > 0) {
                $counts[$sizes[$index]->id] = $count;
                $amount += $count * $sizes[$index]->amount_in_base;
            }
        }

        return new PackageSelection($counts, $amount);
    }

    /**
     * Try every count of the package at the given index and recurse into the
     * smaller sizes, keeping the cheapest complete combination.
     *
     * @param  Collection<int, ProductPackage>  $sizes  Packages ordered from largest to smallest.
     * @param  list<int>  $counts  Counts chosen for the packages before the index.
     * @param  array{cost: float, counts: list<int>}  $best
     */
    private function search(float $need, Collection $sizes, int $index, array $counts, float $covered, array &$best): void
    {
        $size = $sizes[$index]->amount_in_base;
        $toCover = (int) ceil(max($need - $covered, 0) / $size - self::EPSILON);

        if ($index === $sizes->count() - 1) {
            $this->evaluate($need, [...$counts, $toCover], $covered + $toCover * $size, $best);

            return;
        }

        for ($count = 0; $count <= $toCover; $count++) {
            $this->search($need, $sizes, $index + 1, [...$counts, $count], $covered + $count * $size, $best);
        }
    }

    /**
     * @param  list<int>  $counts
     * @param  array{cost: float, counts: list<int>}  $best
     */
    private function evaluate(float $need, array $counts, float $covered, array &$best): void
    {
        $cost = $covered - $need + self::PACKAGE_PENALTY * $need * array_sum($counts);

        if ($cost < $best['cost'] - self::EPSILON) {
            $best = ['cost' => $cost, 'counts' => $counts];
        }
    }
}
