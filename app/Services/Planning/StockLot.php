<?php

namespace App\Services\Planning;

use Carbon\CarbonImmutable;

/**
 * Opened amount of a product together with the day it stops being usable.
 */
final class StockLot
{
    public function __construct(
        public float $amount,
        public readonly ?CarbonImmutable $openedOn,
        public readonly ?CarbonImmutable $expiresOn,
    ) {}

    public function isExpiredOn(CarbonImmutable $date): bool
    {
        return $this->expiresOn !== null && $this->expiresOn->lt($date);
    }
}
