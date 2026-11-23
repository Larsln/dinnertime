<?php

namespace App\Services\Planning;

/**
 * Turns the forecasts of a product and of the products bought as it into
 * the attributes of one shopping list position.
 *
 * The position names the product the others are bought as. Its own
 * amounts fill the position, those of the others are listed as variants,
 * so whoever shops sees every nuance and decides what to take.
 */
class ShoppingItemAttributes
{
    private const array WITHOUT_AMOUNTS = [
        'needed_amount' => null,
        'purchase_amount' => null,
        'packages' => null,
        'leftover_amount' => 0,
        'wasted_amount' => 0,
        'uses' => null,
        'extra_amounts' => null,
    ];

    /**
     * @param  list<ProductForecast>  $parts  Forecasts of the product and of the products bought as it.
     * @return array<string, mixed>
     */
    public function of(int $productId, array $parts): array
    {
        $own = array_find($parts, fn (ProductForecast $part) => $part->product->id === $productId);
        $variants = array_values(array_filter($parts, fn (ProductForecast $part) => $part !== $own));

        return [
            'product_id' => $productId,
            ...($own === null ? self::WITHOUT_AMOUNTS : $this->amounts($own)),
            'is_approximate' => array_any($parts, fn (ProductForecast $part) => $part->approximate),
            'variants' => $variants === [] ? null : array_map(fn (ProductForecast $variant) => [
                'product_id' => $variant->product->id,
                'name' => $variant->product->name,
                'base_unit' => $variant->product->base_unit->value,
                ...$this->amounts($variant),
            ], $variants),
        ];
    }

    /**
     * What to buy of one product and where it goes.
     *
     * @return array<string, mixed>
     */
    private function amounts(ProductForecast $forecast): array
    {
        return [
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
