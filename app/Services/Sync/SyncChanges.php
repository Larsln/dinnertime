<?php

namespace App\Services\Sync;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * One answer of the sync feed.
 */
final readonly class SyncChanges
{
    /**
     * @param  int  $version  Change number up to which the answer is complete.
     * @param  bool  $hasMore  Whether changes after that number exist already.
     * @param  array<string, AnonymousResourceCollection>  $changed  New and changed records by kind.
     * @param  array<string, list<int>>  $deleted  Ids of deleted records by kind.
     */
    public function __construct(
        public int $version,
        public bool $hasMore,
        public array $changed,
        public array $deleted,
    ) {}
}
