<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Lets the later action win when clients change the same record.
 *
 * A client that was offline states in X-Client-Time when the user acted.
 * A change or deletion is turned away when the record was changed by a
 * later action, and every record a request changes remembers the time of
 * the action instead of the time the request arrived.
 */
class HonorActionTime
{
    public const string HEADER = 'X-Client-Time';

    public const string STALE = 'stale';

    private const array CHECKED_METHODS = ['PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $actedAt = $this->actionTime($request);
        $records = array_values(array_filter(
            $request->route()?->parameters() ?? [],
            fn (mixed $parameter) => $parameter instanceof Model && $parameter->usesTimestamps(),
        ));
        $target = end($records);

        if ($target && in_array($request->method(), self::CHECKED_METHODS, true) && $target->getAttribute('updated_at')?->gt($actedAt)) {
            return response()->json(['message' => __('messages.stale_change'), 'code' => self::STALE], 409);
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            foreach ($records as $record) {
                $record->newQuery()->whereKey($record->getKey())->toBase()->update(['updated_at' => $actedAt]);
            }
        }

        return $response;
    }

    /**
     * When the user acted: the stated time, but never in the future.
     */
    private function actionTime(Request $request): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $stated = $request->header(self::HEADER);

        try {
            return $stated ? CarbonImmutable::parse($stated)->min($now) : $now;
        } catch (Throwable) {
            return $now;
        }
    }
}
