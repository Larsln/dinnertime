<?php

namespace Database\Seeders;

use App\Models\StoreSection;
use Illuminate\Database\Seeder;

class StoreSectionSeeder extends Seeder
{
    private const array KEYS = [
        'produce',
        'meat_fish',
        'chilled',
        'bakery',
        'pasta_rice_cereal',
        'baking',
        'spices',
        'sauces_oils_stock',
        'canned',
        'beverages',
        'other',
    ];

    /**
     * Seed the built-in supermarket sections in walking order.
     */
    public function run(): void
    {
        foreach (self::KEYS as $index => $key) {
            StoreSection::firstOrCreate(['key' => $key], ['position' => $index + 1]);
        }
    }
}
