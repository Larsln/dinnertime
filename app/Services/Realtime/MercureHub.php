<?php

namespace App\Services\Realtime;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Publishes updates to the Mercure hub built into the web server and
 * issues the tokens clients subscribe with.
 *
 * All household members share one private topic.
 */
class MercureHub
{
    public const string TOPIC = 'household';

    private const string ALGORITHM = 'HS256';

    private const string TOKEN_TYPE = 'at+jwt';

    private const string AUTHORIZATION_TYPE = 'https://mercure.rocks/authorization-detail';

    private const int SUBSCRIBER_TOKEN_HOURS = 12;

    public function __construct(
        private readonly ?string $key,
        private readonly string $issuer,
        private readonly string $publishUrl,
    ) {}

    /**
     * Whether a signing key is set. Without one clients fall back to
     * refreshing on their own.
     */
    public function isConfigured(): bool
    {
        return filled($this->key);
    }

    /**
     * Send data to every subscribed client. Failures are ignored, because
     * clients catch up the next time they refresh.
     *
     * @param  array<string, mixed>  $data
     */
    public function publish(array $data): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        rescue(fn () => Http::asForm()
            ->withToken($this->token('publish', $this->publishUrl, minutes: 1))
            ->timeout(3)
            ->post($this->publishUrl, [
                'topic' => self::TOPIC,
                'data' => json_encode($data),
                'private' => 'on',
            ]), report: false);
    }

    /**
     * Token that allows a client to listen to the household topic at the
     * hub address it connects to.
     */
    public function subscriberToken(string $hubUrl): string
    {
        return $this->token('subscribe', $hubUrl, minutes: self::SUBSCRIBER_TOKEN_HOURS * 60);
    }

    /**
     * Sign an access token that permits one action on the household topic.
     *
     * The hub expects OAuth access tokens: type "at+jwt", the address it
     * was reached at as audience and the permission as authorization
     * detail. The JWT library used elsewhere cannot set the type.
     */
    private function token(string $action, string $audience, int $minutes): string
    {
        $segments = array_map($this->base64Url(...), [
            json_encode(['typ' => self::TOKEN_TYPE, 'alg' => self::ALGORITHM]),
            json_encode([
                'iss' => $this->issuer,
                'aud' => $audience,
                'sub' => $this->issuer,
                'client_id' => $this->issuer,
                'iat' => now()->timestamp,
                'exp' => now()->addMinutes($minutes)->timestamp,
                'jti' => (string) Str::uuid(),
                'authorization_details' => [[
                    'type' => self::AUTHORIZATION_TYPE,
                    'actions' => [$action],
                    'topics' => [['match' => self::TOPIC, 'match_type' => 'exact']],
                ]],
            ]),
        ]);

        $signature = hash_hmac('sha256', implode('.', $segments), (string) $this->key, binary: true);

        return implode('.', [...$segments, $this->base64Url($signature)]);
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
