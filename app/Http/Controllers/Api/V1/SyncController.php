<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HouseholdSettings;
use App\Services\Sync\SyncFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    private const int DEFAULT_LIMIT = 200;

    /**
     * Report what changed since the change number the client last saw.
     */
    public function __invoke(Request $request, SyncFeed $feed, HouseholdSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'since' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'between:1,500'],
        ]);

        $changes = $feed->changesSince((int) ($data['since'] ?? 0), (int) ($data['limit'] ?? self::DEFAULT_LIMIT), $request->user());

        return response()->json(['data' => [
            'version' => $changes->version,
            'has_more' => $changes->hasMore,
            'changed' => $changes->changed,
            'deleted' => (object) $changes->deleted,
            'household' => ['default_servings' => $settings->defaultServings()],
            'peer_key' => $settings->peerKey(),
        ]]);
    }
}
