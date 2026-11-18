<?php

namespace App\Services\Realtime;

use function Illuminate\Support\defer;

/**
 * Tells connected clients which areas of the household's data changed.
 *
 * Changes are collected while a request or job runs and sent as a single
 * update afterwards, so a save that touches many rows causes one refresh.
 */
class ChangeBroadcaster
{
    private const string DEFERRED_NAME = 'broadcast-changes';

    /**
     * @var array<string, true> Areas changed since the last update was sent.
     */
    private array $areas = [];

    public function __construct(private readonly MercureHub $hub) {}

    /**
     * Note that an area such as "shopping" or "pantry" changed.
     */
    public function changed(string $area): void
    {
        $this->areas[$area] = true;

        defer(fn () => $this->flush(), self::DEFERRED_NAME);
    }

    /**
     * Send the collected areas now.
     */
    public function flush(): void
    {
        if ($this->areas === []) {
            return;
        }

        $this->hub->publish(['areas' => array_keys($this->areas)]);
        $this->areas = [];
    }
}
