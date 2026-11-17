<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies ID tokens of the OpenID Connect provider the instance trusts.
 *
 * Clients run the authorization code flow with PKCE against the provider
 * themselves and hand the resulting ID token to the server.
 */
class OidcVerifier
{
    private const int METADATA_TTL_SECONDS = 3600;

    public function __construct(
        private readonly ?string $issuer,
        private readonly ?string $clientId,
    ) {}

    /**
     * Whether a provider is set up. Without one only local accounts exist.
     */
    public function isConfigured(): bool
    {
        return filled($this->issuer) && filled($this->clientId);
    }

    /**
     * Check signature, issuer, audience and expiry of an ID token.
     *
     * @throws InvalidIdTokenException
     */
    public function verify(string $idToken): OidcIdentity
    {
        try {
            $metadata = $this->metadata();
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($this->keys($metadata['jwks_uri']), 'RS256'));
        } catch (Throwable $exception) {
            throw new InvalidIdTokenException('The ID token could not be verified.', previous: $exception);
        }

        if (($claims['iss'] ?? null) !== $metadata['issuer'] || ! in_array($this->clientId, (array) ($claims['aud'] ?? []), true)) {
            throw new InvalidIdTokenException('The ID token was issued by another provider or for another client.');
        }

        $username = $claims['preferred_username'] ?? $claims['email'] ?? $claims['sub'];

        return new OidcIdentity($claims['sub'], $username, $claims['name'] ?? $username, $claims['email'] ?? null);
    }

    /**
     * Provider metadata from the discovery document.
     *
     * @return array{issuer: string, jwks_uri: string}
     */
    private function metadata(): array
    {
        return Cache::remember("oidc.metadata.{$this->issuer}", self::METADATA_TTL_SECONDS, fn () => Http::acceptJson()
            ->get(rtrim((string) $this->issuer, '/').'/.well-known/openid-configuration')
            ->throw()
            ->json());
    }

    /**
     * Signing keys of the provider.
     *
     * @return array<string, mixed>
     */
    private function keys(string $jwksUri): array
    {
        return Cache::remember("oidc.keys.{$this->issuer}", self::METADATA_TTL_SECONDS, fn () => Http::acceptJson()
            ->get($jwksUri)
            ->throw()
            ->json());
    }
}
