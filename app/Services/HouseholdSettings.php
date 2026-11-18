<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * What the household decided once for everyday use.
 */
class HouseholdSettings
{
    public const int MAX_SERVINGS = 50;

    private const string SERVINGS_KEY = 'household.default_servings';

    private const string PEER_KEY = 'household.peer_key';

    private const int FALLBACK_SERVINGS = 2;

    /**
     * Number of servings a meal is planned and shopped for unless another
     * number is chosen.
     */
    public function defaultServings(): int
    {
        return (int) (Setting::find(self::SERVINGS_KEY)->value ?? self::FALLBACK_SERVINGS);
    }

    /**
     * Secret all signed-in devices of the household share. Devices that
     * meet without a connection to the server use it to recognise each
     * other before they exchange changes directly.
     */
    public function peerKey(): string
    {
        return Setting::firstOrCreate(['key' => self::PEER_KEY], ['value' => Str::random(48)])->value;
    }

    public function setDefaultServings(int $servings): void
    {
        Setting::updateOrCreate(['key' => self::SERVINGS_KEY], ['value' => $servings]);
    }
}
