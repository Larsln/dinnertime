<?php

namespace Database\Factories;

use App\Models\StoreSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreSection>
 */
class StoreSectionFactory extends Factory
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
            'position' => fake()->numberBetween(1, 50),
        ];
    }
}
