<?php

namespace App\Services\Auth;

/**
 * Verified claims of a user signed in at the OIDC provider.
 */
final readonly class OidcIdentity
{
    public function __construct(
        public string $subject,
        public string $username,
        public string $name,
        public ?string $email,
    ) {}
}
