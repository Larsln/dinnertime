<?php

namespace App\Services\Mealie;

use App\Enums\UnitKind;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\StoreSection;
use App\Models\Unit;
use App\Support\ReferenceTranslations;

/**
 * Imports store sections, units and products from Mealie.
 *
 * Records are matched by their Mealie id, so the import can be repeated.
 */
class CatalogImporter
{
    public function __construct(private readonly MealieClient $mealie) {}

    /**
     * Import the catalog and report how many records were written.
     *
     * @return array{sections: int, units: int, products: int}
     */
    public function import(bool $overwrite): array
    {
        $sectionIds = $this->importSections();

        return [
            'sections' => count($sectionIds),
            'units' => $this->importUnits(),
            'products' => $this->importProducts($sectionIds, $overwrite),
        ];
    }

    /**
     * Map Mealie labels onto the built-in store sections by their name in
     * any supported language, appending unknown ones as custom sections.
     *
     * @return array<string, int> Store section ids keyed by Mealie label id.
     */
    private function importSections(): array
    {
        $sectionIds = [];

        foreach ($this->mealie->labels() as $label) {
            $key = ReferenceTranslations::findKey('reference.store_sections', $label['name']);

            $section = StoreSection::firstOrCreate(
                $key !== null ? ['key' => $key] : ['name' => $label['name']],
                ['position' => StoreSection::max('position') + 1],
            );

            $sectionIds[$label['id']] = $section->id;
        }

        return $sectionIds;
    }

    /**
     * Link Mealie units to the built-in units, creating those that are missing.
     */
    private function importUnits(): int
    {
        $mealieUnits = $this->mealie->units();

        foreach ($mealieUnits as $mealieUnit) {
            $unit = Unit::firstWhere('mealie_unit_id', $mealieUnit['id'])
                ?? $this->findBuiltInUnit($mealieUnit)
                ?? new Unit([
                    'code' => $mealieUnit['abbreviation'] ?: $mealieUnit['name'],
                    'name' => $mealieUnit['name'],
                    'plural_name' => $mealieUnit['pluralName'],
                    'kind' => UnitKind::ProductSpecific,
                ]);

            $unit->fill(['mealie_unit_id' => $mealieUnit['id']])->save();
        }

        return count($mealieUnits);
    }

    /**
     * Find the built-in unit a Mealie unit refers to by any of its names.
     *
     * @param  array<string, mixed>  $mealieUnit
     */
    private function findBuiltInUnit(array $mealieUnit): ?Unit
    {
        $labels = array_filter([
            $mealieUnit['name'],
            $mealieUnit['pluralName'],
            $mealieUnit['abbreviation'],
            $mealieUnit['pluralAbbreviation'],
            ...array_column($mealieUnit['aliases'], 'name'),
        ]);

        foreach ($labels as $label) {
            $key = ReferenceTranslations::findKey('reference.units', $label);

            if ($key !== null) {
                return Unit::whereNull('mealie_unit_id')->firstWhere('key', $key);
            }
        }

        return null;
    }

    /**
     * Import foods as products together with their aliases. They stay
     * suggested because Mealie knows neither base unit nor shelf life.
     *
     * @param  array<string, int>  $sectionIds
     */
    private function importProducts(array $sectionIds, bool $overwrite): int
    {
        $written = 0;

        foreach ($this->mealie->foods() as $food) {
            $product = Product::firstWhere('mealie_food_id', $food['id'])
                ?? Product::firstOrNew(['name' => $food['name']]);

            if ($product->mealie_food_id !== null && ! $overwrite) {
                continue;
            }

            $product->fill([
                'name' => $food['name'],
                'plural_name' => $food['pluralName'],
                'store_section_id' => $sectionIds[$food['labelId']] ?? null,
                'mealie_food_id' => $food['id'],
            ])->save();

            foreach ($food['aliases'] as $alias) {
                ProductAlias::updateOrCreate(['name' => $alias['name']], ['product_id' => $product->id]);
            }

            $written++;
        }

        return $written;
    }
}
