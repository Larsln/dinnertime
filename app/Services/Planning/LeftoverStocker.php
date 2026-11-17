<?php

namespace App\Services\Planning;

use App\Models\PantryItem;
use App\Models\ShoppingList;
use Illuminate\Support\Facades\DB;

/**
 * Moves what remains after a shopping list's period into the pantry.
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

        DB::transaction(function () use ($list, $forecast) {
            foreach ($forecast->products as $product) {
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
