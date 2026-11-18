<?php

namespace App\Services\Planning;

use App\Enums\MealType;
use Carbon\CarbonImmutable;

/**
 * One meal on one day that a recipe can be planned for.
 */
final readonly class PlanSlot
{
    public function __construct(
        public CarbonImmutable $date,
        public MealType $meal,
    ) {}

    /**
     * Identifies the slot, e.g. "2026-11-18|dinner".
     */
    public function key(): string
    {
        return "{$this->date->toDateString()}|{$this->meal->value}";
    }

    public function isOnWeekday(): bool
    {
        return $this->date->isWeekday();
    }
}
