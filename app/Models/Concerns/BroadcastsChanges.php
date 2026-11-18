<?php

namespace App\Models\Concerns;

use App\Services\Realtime\ChangeBroadcaster;

/**
 * Announces to connected clients that the model's area changed whenever a
 * record is saved or deleted.
 */
trait BroadcastsChanges
{
    /**
     * Area clients refresh when a record of this model changes.
     */
    abstract public static function broadcastArea(): string;

    public static function bootBroadcastsChanges(): void
    {
        $announce = fn () => app(ChangeBroadcaster::class)->changed(static::broadcastArea());

        static::saved($announce);
        static::deleted($announce);
    }
}
