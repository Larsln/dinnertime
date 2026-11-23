<?php

namespace App\Services\Planning;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates or refreshes the shopping list of a period from the meal plan.
 *
 * Only what has not been bought yet changes. Checked positions stay as
 * they are and count as stock, so a refresh adds a position for what is
 * missing beyond them. Manual positions survive a refresh. An open
 * position keeps its record, so changes clients made to it offline still
 * find it. Products bought as another one share its position.
 */
class ShoppingListSynchronizer
{
    public function __construct(
        private readonly ShoppingForecaster $forecaster,
        private readonly ShoppingItemAttributes $attributes,
    ) {}

    public function sync(CarbonImmutable $from, CarbonImmutable $to): ShoppingList
    {
        return DB::transaction(function () use ($from, $to) {
            $list = ShoppingList::firstOrCreate(['starts_on' => $from, 'ends_on' => $to]);
            [$checked, $open] = $list->items()->where('is_manual', false)->get()
                ->partition(fn (ShoppingListItem $item) => $item->checked_at !== null);
            $forecast = $this->forecaster->forecastPeriod($from, $to, $this->boughtAmounts($checked));
            $kept = [];

            foreach ($this->positions($forecast, $this->boughtProducts($checked)) as $productId => $parts) {
                $item = $open->firstWhere('product_id', $productId) ?? $list->items()->make();
                $item->fill($this->attributes->of($productId, $parts))->save();
                $kept[] = $item->id;
            }

            foreach ($forecast->unresolved as $text) {
                if (! $checked->whereNull('product_id')->contains('label', $text)) {
                    $kept[] = ($open->whereNull('product_id')->firstWhere('label', $text) ?? $list->items()->create(['label' => $text]))->id;
                }
            }

            $open->whereNotIn('id', $kept)->each->delete();

            return $list;
        });
    }

    /**
     * Forecasts that call for a purchase, grouped by the product they are
     * bought as. Needs without an amount count as covered once a position
     * of the product was checked off.
     *
     * @param  array<int, true>  $bought  Ids of products that were checked off.
     * @return array<int, list<ProductForecast>>
     */
    private function positions(ShoppingForecast $forecast, array $bought): array
    {
        $positions = [];

        foreach ($forecast->products as $product) {
            $missing = $product->purchased > 0 || (! isset($bought[$product->product->id]) && $product->requiresPurchase());

            if ($missing) {
                $positions[$product->product->buy_as_id ?? $product->product->id][] = $product;
            }
        }

        return $positions;
    }

    /**
     * Amounts that checked positions bought, keyed by product id.
     *
     * @param  Collection<int, ShoppingListItem>  $checked
     * @return array<int, float>
     */
    private function boughtAmounts(Collection $checked): array
    {
        $amounts = [];

        foreach ($this->purchases($checked) as [$productId, $amount]) {
            $amounts[$productId] = ($amounts[$productId] ?? 0) + $amount;
        }

        return array_filter($amounts, fn (float $amount) => $amount > 0);
    }

    /**
     * @param  Collection<int, ShoppingListItem>  $checked
     * @return array<int, true>
     */
    private function boughtProducts(Collection $checked): array
    {
        return array_fill_keys(array_column($this->purchases($checked), 0), true);
    }

    /**
     * Product and amount of everything checked positions bought, their
     * variants included.
     *
     * @param  Collection<int, ShoppingListItem>  $checked
     * @return list<array{0: int, 1: float}>
     */
    private function purchases(Collection $checked): array
    {
        $purchases = [];

        foreach ($checked->whereNotNull('product_id') as $item) {
            $purchases[] = [$item->product_id, (float) $item->purchase_amount];

            foreach ($item->variants ?? [] as $variant) {
                $purchases[] = [$variant['product_id'], (float) ($variant['purchase_amount'] ?? 0)];
            }
        }

        return $purchases;
    }
}
