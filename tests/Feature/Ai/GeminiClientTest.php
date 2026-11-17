<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    private const array SCHEMA = ['type' => 'OBJECT'];

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        Http::preventStrayRequests();
    }

    public function test_it_requests_json_matching_the_schema(): void
    {
        Http::fake(['*' => Http::response($this->answer('{"ok": true}'))]);

        $result = $this->client()->generate('model-x', [['text' => 'Hi']], self::SCHEMA);

        $this->assertSame(['ok' => true], $result);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://gemini.test/v1beta/models/model-x:generateContent'
            && $request->hasHeader('x-goog-api-key', 'secret')
            && $request['contents'][0]['parts'][0]['text'] === 'Hi'
            && $request['generationConfig']['responseSchema'] === self::SCHEMA);
    }

    public function test_it_retries_blocked_and_overloaded_requests(): void
    {
        Http::fakeSequence()
            ->push(['promptFeedback' => ['blockReason' => 'PROHIBITED_CONTENT']])
            ->push('overloaded', 503)
            ->push($this->answer('{"ok": true}'));

        $this->assertSame(['ok' => true], $this->client()->generate('model-x', [], self::SCHEMA));
        Http::assertSentCount(3);
    }

    public function test_it_reports_the_block_reason_after_the_last_attempt(): void
    {
        Http::fake(['*' => Http::response(['promptFeedback' => ['blockReason' => 'PROHIBITED_CONTENT']])]);

        $this->expectException(GeminiException::class);
        $this->expectExceptionMessage('PROHIBITED_CONTENT');

        $this->client()->generate('model-x', [], self::SCHEMA);
    }

    public function test_it_does_not_retry_rejected_requests(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

        try {
            $this->client()->generate('model-x', [], self::SCHEMA);
            $this->fail('Expected the request to be rejected.');
        } catch (RequestException) {
            Http::assertSentCount(1);
        }
    }

    public function test_it_refuses_to_run_without_a_key(): void
    {
        $client = new GeminiClient(null, 'https://gemini.test');

        $this->assertFalse($client->isConfigured());
        $this->expectException(GeminiException::class);

        $client->generate('model-x', [], self::SCHEMA);
    }

    private function client(): GeminiClient
    {
        return new GeminiClient('secret', 'https://gemini.test');
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(string $json): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $json]]]]]];
    }
}
