<?php

namespace App\Services;

use App\Models\Setting;

/**
 * What the household decided once for everyday use.
 */
class HouseholdSettings
{
    public const int MAX_SERVINGS = 50;

    private const string SERVINGS_KEY = 'household.default_servings';

    private const int FALLBACK_SERVINGS = 2;

    /**
     * Number of servings a meal is planned and shopped for unless another
     * number is chosen.
     */
    public function defaultServings(): int
    {
        return (int) (Setting::find(self::SERVINGS_KEY)->value ?? self::FALLBACK_SERVINGS);
    }

    public function setDefaultServings(int $servings): void
    {
        Setting::updateOrCreate(['key' => self::SERVINGS_KEY], ['value' => $servings]);
    }
}
