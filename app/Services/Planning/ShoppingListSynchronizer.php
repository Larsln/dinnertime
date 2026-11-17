<?php

namespace App\Services\Planning;

use App\Models\ProductPackage;
use App\Models\ShoppingList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates or refreshes the shopping list of a period from the meal plan.
 *
 * Manual positions survive a refresh, and products that were already
 * checked off stay checked.
 */
class ShoppingListSynchronizer
{
    public function __construct(private readonly ShoppingForecaster $forecaster) {}

    public function sync(CarbonImmutable $from, CarbonImmutable $to): ShoppingList
    {
        $forecast = $this->forecaster->forecastPeriod($from, $to);

        return DB::transaction(function () use ($from, $to, $forecast) {
            $list = ShoppingList::firstOrCreate(['starts_on' => $from, 'ends_on' => $to]);
            $checked = $list->items()->whereNotNull('checked_at')->whereNotNull('product_id')->get()->keyBy('product_id');

            $list->items()->where('is_manual', false)->delete();

            foreach ($forecast->products as $product) {
                if ($product->requiresPurchase()) {
                    $list->items()->create([
                        ...$this->itemAttributes($product),
                        'checked_at' => $checked->get($product->product->id)?->checked_at,
                        'checked_by' => $checked->get($product->product->id)?->checked_by,
                    ]);
                }
            }

            foreach ($forecast->unresolved as $text) {
                $list->items()->create(['label' => $text]);
            }

            return $list;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function itemAttributes(ProductForecast $forecast): array
    {
        return [
            'product_id' => $forecast->product->id,
            'needed_amount' => $forecast->needed ?: null,
            'purchase_amount' => $forecast->purchased ?: null,
            'packages' => $this->packageRows($forecast),
            'leftover_amount' => $forecast->leftover,
            'wasted_amount' => $forecast->wasted,
            'uses' => array_map(fn (Demand $demand) => [
                'date' => $demand->date->toDateString(),
                'recipe_id' => $demand->recipe->id,
                'title' => $demand->recipe->title,
                'amount' => round($demand->amount, 3),
            ], $forecast->uses),
            'is_approximate' => $forecast->approximate,
        ];
    }

    /**
     * @return list<array{package_id: int, label: string, amount: float, count: int}>
     */
    private function packageRows(ProductForecast $forecast): array
    {
        return $forecast->product->packages
            ->filter(fn (ProductPackage $package) => isset($forecast->packageCounts[$package->id]))
            ->sortByDesc('amount_in_base')
            ->map(fn (ProductPackage $package) => [
                'package_id' => $package->id,
                'label' => $package->label,
                'amount' => $package->amount_in_base,
                'count' => $forecast->packageCounts[$package->id],
            ])
            ->values()
            ->all();
    }
}
