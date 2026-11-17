<?php

namespace App\Services\Planning;

use App\Models\Recipe;
use Carbon\CarbonImmutable;

/**
 * Amount of a product a recipe consumes on a day.
 */
final readonly class Demand
{
    public function __construct(
        public CarbonImmutable $date,
        public Recipe $recipe,
        public float $amount,
    ) {}
}
