<?php

namespace App\Services\Planning;

use App\Models\ShoppingList;
use Carbon\CarbonImmutable;

/**
 * Keeps shopping lists in step with the meal plan and the catalog: when
 * meals on some days change, every list covering one of those days is
 * refreshed, and when products are grouped differently, every list still
 * running. Lists whose leftovers went to the pantry are closed and stay as
 * they are.
 */
class ShoppingListRefresher
{
    public function __construct(private readonly ShoppingListSynchronizer $synchronizer) {}

    /**
     * @param  iterable<CarbonImmutable>  $days  Days whose meals changed.
     */
    public function afterChangesOn(iterable $days): void
    {
        $dates = collect($days)->map(fn (CarbonImmutable $day) => $day->toDateString())->unique();

        if ($dates->isEmpty()) {
            return;
        }

        ShoppingList::query()
            ->whereNull('leftovers_stocked_at')
            ->whereDate('starts_on', '<=', $dates->max())
            ->whereDate('ends_on', '>=', $dates->min())
            ->get()
            ->filter(fn (ShoppingList $list) => $dates->contains(fn (string $date) => $date >= $list->starts_on->toDateString() && $date <= $list->ends_on->toDateString()))
            ->each(fn (ShoppingList $list) => $this->synchronizer->sync($list->starts_on, $list->ends_on));
    }

    /**
     * Refresh the open lists whose period has not ended yet.
     */
    public function running(): void
    {
        ShoppingList::query()
            ->whereNull('leftovers_stocked_at')
            ->whereDate('ends_on', '>=', CarbonImmutable::today())
            ->get()
            ->each(fn (ShoppingList $list) => $this->synchronizer->sync($list->starts_on, $list->ends_on));
    }
}
