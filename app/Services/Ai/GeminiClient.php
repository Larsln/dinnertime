<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Client for structured output from the native Gemini API.
 */
class GeminiClient
{
    /**
     * Gemini sporadically blocks harmless input or is briefly overloaded,
     * so every request is attempted several times.
     */
    private const int ATTEMPTS = 3;

    private const int BACKOFF_SECONDS = 2;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
    ) {}

    /**
     * Whether an API key is set. Without one all AI features are disabled.
     */
    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Ask a model for JSON matching the schema.
     *
     * @param  list<array<string, mixed>>  $parts  Content parts such as text or file references.
     * @param  array<string, mixed>  $schema  Response schema in Gemini's OpenAPI subset.
     * @return array<string, mixed>
     *
     * @throws GeminiException
     */
    public function generate(string $model, array $parts, array $schema): array
    {
        if (! $this->isConfigured()) {
            throw GeminiException::notConfigured();
        }

        $failure = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $response = $this->request()->post("/v1beta/models/{$model}:generateContent", [
                'contents' => [['parts' => $parts]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                ],
            ]);

            $failure = $this->failureOf($response);

            if ($failure === null) {
                return json_decode($response->json('candidates.0.content.parts.0.text'), true);
            }

            if ($response->clientError() && $response->status() !== 429) {
                $response->throw();
            }

            if ($attempt < self::ATTEMPTS) {
                Sleep::for(self::BACKOFF_SECONDS * $attempt)->seconds();
            }
        }

        throw $failure;
    }

    /**
     * Describe why a response is unusable, or null when it carries JSON.
     */
    private function failureOf(Response $response): ?GeminiException
    {
        if (! $response->successful()) {
            return GeminiException::unavailable($response->status());
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if ($text === null) {
            return GeminiException::blocked(
                $response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason'),
            );
        }

        return is_array(json_decode($text, true)) ? null : GeminiException::malformed();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->acceptJson()
            ->timeout(120);
    }
}
