<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Realtime\MercureHub;
use Illuminate\Http\JsonResponse;

class RealtimeController extends Controller
{
    /**
     * Tell the client where to listen for changes and hand it the token to
     * send as bearer token when it connects.
     * Answers 404 on instances without realtime updates.
     */
    public function __invoke(MercureHub $hub): JsonResponse
    {
        abort_unless($hub->isConfigured(), 404);

        $hubUrl = url('/.well-known/mercure');

        return response()->json(['data' => [
            'url' => $hubUrl.'?'.http_build_query(['match' => MercureHub::TOPIC]),
            'token' => $hub->subscriberToken($hubUrl),
        ]]);
    }
}
