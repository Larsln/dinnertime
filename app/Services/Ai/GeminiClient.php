<?php

namespace App\Services\Ai;

use GuzzleHttp\Psr7\Utils;
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

    private const int UPLOAD_TIMEOUT_SECONDS = 300;

    private const int PROCESSING_POLLS = 90;

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
     * Upload a local file and wait until Gemini has finished processing it.
     *
     * @throws GeminiException
     */
    public function uploadFile(string $path, string $mimeType): GeminiFile
    {
        $session = $this->request()
            ->withHeaders([
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) filesize($path),
                'X-Goog-Upload-Header-Content-Type' => $mimeType,
            ])
            ->post('/upload/v1beta/files', ['file' => ['display_name' => basename($path)]])
            ->throw();

        $file = Http::withHeaders(['X-Goog-Upload-Offset' => '0', 'X-Goog-Upload-Command' => 'upload, finalize'])
            ->withBody(Utils::streamFor(fopen($path, 'r')), $mimeType)
            ->timeout(self::UPLOAD_TIMEOUT_SECONDS)
            ->post($session->header('X-Goog-Upload-URL'))
            ->throw()
            ->json('file');

        for ($poll = 0; $file['state'] === 'PROCESSING' && $poll < self::PROCESSING_POLLS; $poll++) {
            Sleep::for(self::BACKOFF_SECONDS)->seconds();
            $file = $this->request()->get("/v1beta/{$file['name']}")->throw()->json();
        }

        if ($file['state'] !== 'ACTIVE') {
            throw GeminiException::fileNotProcessed($file['state']);
        }

        return new GeminiFile($file['name'], $file['uri'], $file['mimeType']);
    }

    /**
     * Delete an uploaded file. Failures are ignored because Gemini removes
     * files on its own after two days.
     */
    public function deleteFile(GeminiFile $file): void
    {
        $this->request()->delete("/v1beta/{$file->name}");
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
