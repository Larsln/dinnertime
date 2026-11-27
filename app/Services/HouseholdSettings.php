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

    private const string STOCKED_KEY = 'household.show_stocked_needs';

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
     * Whether the shopping list also notes what the meals need that is at
     * home already.
     */
    public function showsStockedNeeds(): bool
    {
        return (bool) (Setting::find(self::STOCKED_KEY)->value ?? false);
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

    /**
     * ISO 4217 code of the currency prices are noted in.
     */
    public function currency(): string
    {
        return config('app.currency');
    }

    public function setDefaultServings(int $servings): void
    {
        Setting::updateOrCreate(['key' => self::SERVINGS_KEY], ['value' => $servings]);
    }

    public function setShowsStockedNeeds(bool $shown): void
    {
        Setting::updateOrCreate(['key' => self::STOCKED_KEY], ['value' => $shown]);
    }

    /**
     * What clients are told about the household.
     *
     * @return array{default_servings: int, currency: string, show_stocked_needs: bool}
     */
    public function toArray(): array
    {
        return [
            'default_servings' => $this->defaultServings(),
            'currency' => $this->currency(),
            'show_stocked_needs' => $this->showsStockedNeeds(),
        ];
    }
}
