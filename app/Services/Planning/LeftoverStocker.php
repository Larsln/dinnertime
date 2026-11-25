<?php

namespace App\Services\Planning;

use App\Models\PantryItem;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

/**
 * Moves what remains after a shopping list's period into the pantry.
 *
 * Products whose position was skipped or replaced by a substitute keep
 * their stock as it is: they were not bought, and nobody knows how much
 * of what was there got used.
 */
class LeftoverStocker
{
    public function __construct(private readonly ShoppingForecaster $forecaster) {}

    /**
     * Replace the pantry stock of every product the period used with the
     * amounts the forecast expects to remain.
     */
    public function stock(ShoppingList $list): void
    {
        $forecast = $this->forecaster->forecastPeriod($list->starts_on, $list->ends_on);

        $dropped = $list->items
            ->reject(fn (ShoppingListItem $item) => $item->is_manual || ! $item->isSettled() || $item->isBought())
            ->flatMap(fn (ShoppingListItem $item) => $item->productIds());

        DB::transaction(function () use ($list, $forecast, $dropped) {
            foreach ($forecast->products as $product) {
                if ($dropped->contains($product->product->id)) {
                    continue;
                }

                PantryItem::where('product_id', $product->product->id)->delete();

                foreach ($product->remainingLots as $lot) {
                    PantryItem::create([
                        'product_id' => $product->product->id,
                        'amount' => $lot->amount,
                        'opened_on' => $lot->openedOn,
                        'expires_on' => $lot->expiresOn,
                    ]);
                }
            }

            $list->update(['leftovers_stocked_at' => now()]);
        });
    }
}
