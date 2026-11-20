<?php

namespace Database\Seeders;

use App\Enums\UnitKind;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    private const array UNITS = [
        'gram' => [UnitKind::Mass, 1],
        'kilogram' => [UnitKind::Mass, 1000],
        'milliliter' => [UnitKind::Volume, 1],
        'liter' => [UnitKind::Volume, 1000],
        'tablespoon' => [UnitKind::ProductSpecific, null],
        'teaspoon' => [UnitKind::ProductSpecific, null],
        'cup' => [UnitKind::ProductSpecific, null],
        'pinch' => [UnitKind::ProductSpecific, null],
        'piece' => [UnitKind::ProductSpecific, null],
        'bunch' => [UnitKind::ProductSpecific, null],
        'clove' => [UnitKind::ProductSpecific, null],
        'can' => [UnitKind::ProductSpecific, null],
        'tub' => [UnitKind::ProductSpecific, null],
        'packet' => [UnitKind::ProductSpecific, null],
    ];

    /**
     * Seed the built-in units recipes are allowed to use.
     */
    public function run(): void
    {
        foreach (self::UNITS as $key => [$kind, $factor]) {
            Unit::updateOrCreate(['key' => $key], ['kind' => $kind, 'factor_to_base' => $factor]);
        }
    }
}
