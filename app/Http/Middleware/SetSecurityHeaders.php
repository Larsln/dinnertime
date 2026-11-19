<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the headers that tell browsers what the web client may load and
 * where it may be shown.
 *
 * The content security policy only allows scripts from this server, so an
 * injected script could neither run nor send the stored token elsewhere.
 * Connections go to this server, which includes the realtime hub, and to
 * the identity provider, whose discovery and token endpoints the browser
 * calls during sign-in.
 */
class SetSecurityHeaders
{
    private const int HSTS_SECONDS = 31536000;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'DENY');

        if (! Vite::isRunningHot()) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_SECONDS);
        }

        return $response;
    }

    /**
     * Reka UI positions popovers with inline styles, so styles may be
     * inline; scripts may not.
     */
    private function contentSecurityPolicy(): string
    {
        $directives = [
            'default-src' => "'self'",
            'script-src' => "'self'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data: blob:",
            'font-src' => "'self'",
            'connect-src' => trim("'self' ".$this->identityProviderOrigin()),
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'none'",
        ];

        return collect($directives)->map(fn (string $sources, string $name) => "{$name} {$sources}")->implode('; ');
    }

    /**
     * Scheme, host and port of the configured issuer, or an empty string
     * without one.
     */
    private function identityProviderOrigin(): string
    {
        $issuer = parse_url((string) config('services.oidc.issuer'));

        if (! isset($issuer['scheme'], $issuer['host'])) {
            return '';
        }

        return "{$issuer['scheme']}://{$issuer['host']}".(isset($issuer['port']) ? ":{$issuer['port']}" : '');
    }
}
