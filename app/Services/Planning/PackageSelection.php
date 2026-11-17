<?php

namespace App\Services\Planning;

/**
 * Packages chosen to cover a needed amount.
 */
final readonly class PackageSelection
{
    /**
     * @param  array<int, int>  $counts  Number of packages keyed by package id.
     * @param  float  $amount  Total amount the packages contain.
     */
    public function __construct(
        public array $counts,
        public float $amount,
    ) {}
}
