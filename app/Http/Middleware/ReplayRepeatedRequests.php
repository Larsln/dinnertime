<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs a changing request that carries an Idempotency-Key only once.
 *
 * A client that was offline or lost the answer sends the request again
 * under the same key and gets the first answer back.
 */
class ReplayRepeatedRequests
{
    public const string HEADER = 'Idempotency-Key';

    private const int MAX_KEY_LENGTH = 100;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if ($request->isMethodSafe() || blank($key) || strlen($key) > self::MAX_KEY_LENGTH) {
            return $next($request);
        }

        $key = "{$request->user()?->id}:{$key}";
        $stored = IdempotencyKey::find($key);

        if ($stored !== null) {
            return response($stored->body, $stored->status, ['Content-Type' => 'application/json']);
        }

        $response = $next($request);

        if (! $response->isServerError()) {
            IdempotencyKey::firstOrCreate(['key' => $key], [
                'status' => $response->getStatusCode(),
                'body' => (string) $response->getContent(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
