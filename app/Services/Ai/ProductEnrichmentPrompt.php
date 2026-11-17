<?php

namespace App\Services\Ai;

use App\Enums\BaseUnit;
use App\Models\Product;
use App\Models\StoreSection;
use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Prompt and response schema for suggesting the purchase details of a product.
 */
final readonly class ProductEnrichmentPrompt
{
    /**
     * @param  Collection<int, Unit>  $units  Recipe units that need a conversion for the product.
     */
    public function __construct(
        private Product $product,
        private Collection $units,
    ) {}

    public function text(): string
    {
        $language = config('app.content_locale');
        $units = $this->units->map(self::unitLabel(...))->implode(', ');

        $lines = [
            'You maintain the grocery catalog of a private household.',
            "The household writes in the language with the code \"{$language}\" and shops in supermarkets where that language is spoken.",
            "Product: \"{$this->product->name}\"",
            '',
            'State typical values for this product:',
            '- store_section: the supermarket section where it is found.',
            '- base_unit: "g" for products bought by weight, "ml" for liquids bought by volume, "piece" for products bought by count such as eggs or lemons.',
            '- is_pantry_staple: true only for basics every household has in stock and never writes on a shopping list, such as salt, pepper, tap water or cooking oil.',
            '- shelf_life_opened_days: number of days the product stays good after it was opened or cut, stored correctly. Use null if it keeps for months.',
            '- packages: up to four common retail package sizes with a short label in the household language and the amount in the base unit. For products sold by the piece include a package with the amount 1.',
        ];

        if ($units !== '') {
            $lines[] = "- conversions: for each of these recipe units, the amount of the product in the base unit that one unit corresponds to: {$units}.";
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $properties = [
            'store_section' => ['type' => 'STRING', 'enum' => StoreSection::whereNotNull('key')->orderBy('position')->pluck('key')->all()],
            'base_unit' => ['type' => 'STRING', 'enum' => array_column(BaseUnit::cases(), 'value')],
            'is_pantry_staple' => ['type' => 'BOOLEAN'],
            'shelf_life_opened_days' => ['type' => 'INTEGER', 'nullable' => true],
            'packages' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['label' => ['type' => 'STRING'], 'amount' => ['type' => 'NUMBER']],
                    'required' => ['label', 'amount'],
                ],
            ],
        ];

        if ($this->units->isNotEmpty()) {
            $properties['conversions'] = [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'unit' => ['type' => 'STRING', 'enum' => $this->units->map(self::unitLabel(...))->all()],
                        'amount' => ['type' => 'NUMBER'],
                    ],
                    'required' => ['unit', 'amount'],
                ],
            ];
        }

        return ['type' => 'OBJECT', 'properties' => $properties, 'required' => array_keys($properties)];
    }

    /**
     * Name a unit is referred to in the prompt and in the answer.
     */
    public static function unitLabel(Unit $unit): string
    {
        return $unit->key ?? $unit->displayCode();
    }
}
