<?php

namespace Tests\Feature;

use App\Models\PantryItem;
use App\Models\Product;
use App\Models\Tool;
use App\Models\User;
use App\Services\Realtime\ChangeBroadcaster;
use App\Services\Realtime\MercureHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private const string KEY = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.mercure.key' => self::KEY]);
        Http::preventStrayRequests();
        Http::fake(['app/.well-known/mercure' => Http::response('urn:uuid:1')]);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_clients_get_a_subscriber_token_for_the_hub_they_reach(): void
    {
        $access = $this->getJson('/api/v1/realtime')->assertOk()->json('data');

        $this->assertStringEndsWith('/.well-known/mercure?match=household', $access['url']);

        [$header, $claims, $signature] = array_map($this->decode(...), explode('.', $access['token']));
        [$signedHeader, $signedClaims] = explode('.', $access['token']);

        $this->assertSame(['typ' => 'at+jwt', 'alg' => 'HS256'], json_decode($header, true));
        $this->assertSame(hash_hmac('sha256', "{$signedHeader}.{$signedClaims}", self::KEY, true), $signature);

        $claims = json_decode($claims, true);
        $this->assertSame(strtok($access['url'], '?'), $claims['aud']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertSame(['subscribe'], $claims['authorization_details'][0]['actions']);
        $this->assertSame([['match' => 'household', 'match_type' => 'exact']], $claims['authorization_details'][0]['topics']);
    }

    public function test_changes_of_one_request_are_published_together(): void
    {
        PantryItem::create(['product_id' => Product::factory()->create()->id, 'amount' => 5]);
        PantryItem::create(['product_id' => Product::factory()->create()->id, 'amount' => 7]);
        Tool::create(['name' => 'Wok']);

        app(ChangeBroadcaster::class)->flush();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['topic'] === MercureHub::TOPIC
            && $request['private'] === 'on'
            && json_decode($request['data'], true) === ['areas' => ['products', 'pantry', 'tools']]
            && str_starts_with($request->header('Authorization')[0], 'Bearer '));
    }

    public function test_nothing_is_published_and_the_endpoint_is_absent_without_a_key(): void
    {
        config(['services.mercure.key' => null]);
        $this->app->forgetInstance(MercureHub::class);
        $this->app->forgetScopedInstances();

        Tool::create(['name' => 'Wok']);
        app(ChangeBroadcaster::class)->flush();

        Http::assertNothingSent();
        $this->getJson('/api/v1/realtime')->assertNotFound();
    }

    private function decode(string $segment): string
    {
        return base64_decode(strtr($segment, '-_', '+/'));
    }
}
