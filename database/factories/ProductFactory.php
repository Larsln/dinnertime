<?php

namespace Database\Factories;

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'base_unit' => BaseUnit::Gram,
            'status' => ReviewStatus::Confirmed,
        ];
    }
}
