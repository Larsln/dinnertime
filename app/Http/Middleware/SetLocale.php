<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers in the first supported language of the Accept-Language header.
 *
 * Requests that name no supported language keep the locale the instance is
 * configured with.
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = collect($request->getLanguages())
            ->map(fn (string $language) => substr($language, 0, 2))
            ->first(fn (string $language) => in_array($language, config('app.supported_locales'), true));

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
