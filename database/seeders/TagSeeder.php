<?php

namespace Database\Seeders;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    private const array KEYS = [
        TagType::Meal->value => [
            'breakfast', 'lunch', 'dinner', 'dessert', 'snack',
        ],
        TagType::Category->value => [
            'breakfast', 'lunch', 'dinner', 'dessert', 'snack',
            'side', 'salad', 'soup', 'baking', 'beverage',
        ],
        TagType::Tag->value => [
            'vegetarian', 'vegan', 'quick', 'low_carb', 'high_protein',
            'meal_prep', 'asian', 'italian', 'mexican', 'middle_eastern',
            'german', 'summer', 'winter', 'kids', 'party',
        ],
    ];

    /**
     * Seed the fixed meal types, categories and tags.
     */
    public function run(): void
    {
        foreach (self::KEYS as $type => $keys) {
            foreach ($keys as $index => $key) {
                Tag::updateOrCreate(['type' => $type, 'key' => $key], ['position' => $index + 1]);
            }
        }
    }
}
