<?php

namespace App\Services\Planning;

/**
 * Lists what the meals of a period need that does not have to be bought,
 * because the pantry covers it or because it is permanently in stock.
 *
 * Whoever shops can see these next to the positions to buy and check that
 * they really are at home.
 */
class StockedNeeds
{
    /**
     * @param  array<int, true>  $settled  Ids of products a settled position of the list already answers for.
     * @return list<array<string, mixed>>
     */
    public function of(ShoppingForecast $forecast, array $settled): array
    {
        $rows = [];

        foreach ($forecast->products as $product) {
            if (! isset($settled[$product->product->id]) && ! $product->requiresPurchase()) {
                $rows[] = $this->row($product->product->id, $product->product->name, $product->product->base_unit->value, $product->needed ?: null, false, $product->uses);
            }
        }

        foreach ($forecast->staples as $staple) {
            $rows[] = $this->row($staple->product->id, $staple->product->name, $staple->product->base_unit->value, null, true, $staple->uses);
        }

        usort($rows, fn (array $a, array $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));

        return $rows;
    }

    /**
     * @param  list<Demand>  $uses
     * @return array<string, mixed>
     */
    private function row(int $productId, string $name, string $baseUnit, ?float $needed, bool $staple, array $uses): array
    {
        return [
            'product_id' => $productId,
            'name' => $name,
            'base_unit' => $baseUnit,
            'needed_amount' => $needed === null ? null : round($needed, 3),
            'is_staple' => $staple,
            'uses' => array_map(fn (Demand $demand) => [
                'date' => $demand->date->toDateString(),
                'recipe_id' => $demand->recipe->id,
                'title' => $demand->recipe->title,
                'amount' => $staple ? null : round($demand->amount, 3),
            ], $uses),
        ];
    }
}
