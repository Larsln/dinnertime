<?php

namespace Tests\Support;

use App\Services\Import\HostResolver;

/**
 * Answers host lookups without asking DNS. Hosts point to a public address
 * unless a test says otherwise.
 */
class FakeHostResolver extends HostResolver
{
    public const string PUBLIC_ADDRESS = '93.184.215.14';

    /**
     * @param  array<string, list<string>>  $hosts
     */
    public function __construct(public array $hosts = []) {}

    public function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return $this->hosts[$host] ?? [self::PUBLIC_ADDRESS];
    }
}
