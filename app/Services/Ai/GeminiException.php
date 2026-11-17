<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Raised when Gemini gives no usable answer after all attempts.
 */
class GeminiException extends RuntimeException
{
    /**
     * The model refused the request or returned no content.
     */
    public static function blocked(?string $reason): self
    {
        return new self('Gemini returned no content'.($reason ? " ({$reason})" : '').'.');
    }

    /**
     * The answer could not be read as the requested JSON structure.
     */
    public static function malformed(): self
    {
        return new self('Gemini returned an answer that is not valid JSON.');
    }

    /**
     * The service was overloaded, rate limited or failing.
     */
    public static function unavailable(int $status): self
    {
        return new self("Gemini is unavailable (HTTP {$status}).");
    }

    public static function notConfigured(): self
    {
        return new self('No Gemini API key is configured.');
    }
}
