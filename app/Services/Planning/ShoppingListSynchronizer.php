<?php

namespace App\Services\Planning;

use App\Models\ShoppingList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates or refreshes the shopping list of a period from the meal plan.
 *
 * Manual positions survive a refresh. A product keeps its position, so it
 * stays checked and changes clients made to it offline still find it.
 */
class ShoppingListSynchronizer
{
    public function __construct(private readonly ShoppingForecaster $forecaster) {}

    public function sync(CarbonImmutable $from, CarbonImmutable $to): ShoppingList
    {
        $forecast = $this->forecaster->forecastPeriod($from, $to);

        return DB::transaction(function () use ($from, $to, $forecast) {
            $list = ShoppingList::firstOrCreate(['starts_on' => $from, 'ends_on' => $to]);
            $existing = $list->items()->where('is_manual', false)->get();
            $kept = [];

            foreach ($forecast->products as $product) {
                if ($product->requiresPurchase()) {
                    $item = $existing->firstWhere('product_id', $product->product->id) ?? $list->items()->make();
                    $item->fill($this->itemAttributes($product))->save();
                    $kept[] = $item->id;
                }
            }

            foreach ($forecast->unresolved as $text) {
                $item = $existing->whereNull('product_id')->firstWhere('label', $text) ?? $list->items()->create(['label' => $text]);
                $kept[] = $item->id;
            }

            $existing->whereNotIn('id', $kept)->each->delete();

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
            'extra_amounts' => array_map(fn (UnconvertedAmount $amount) => [
                'unit_id' => $amount->unitId,
                'quantity' => round($amount->quantity, 3),
            ], $forecast->unconverted),
        ];
    }

    /**
     * @return list<array{package_id: int, label: string, amount: float, count: int}>
     */
    private function packageRows(ProductForecast $forecast): array
    {
        return $forecast->product->buyableSizes()
            ->filter(fn (PackageSize $package) => isset($forecast->packageCounts[$package->id]))
            ->sortByDesc('amount')
            ->map(fn (PackageSize $package) => [
                'package_id' => $package->id,
                'label' => $package->label,
                'amount' => $package->amount,
                'count' => $forecast->packageCounts[$package->id],
            ])
            ->values()
            ->all();
    }
}
