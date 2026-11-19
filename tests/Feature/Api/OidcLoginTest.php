<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Auth\OidcVerifier;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

class OidcLoginTest extends TestCase
{
    use RefreshDatabase;

    private const string ISSUER = 'https://idp.test/application/o/dinnertime/';

    private const string CLIENT_ID = 'dinnertime-client';

    private OpenSSLAsymmetricKey $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($this->key)['rsa'];

        config(['services.oidc' => ['issuer' => self::ISSUER, 'client_id' => self::CLIENT_ID]]);
        Http::preventStrayRequests();
        Http::fake([
            'idp.test/application/o/dinnertime/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'jwks_uri' => 'https://idp.test/jwks',
            ]),
            'idp.test/jwks' => Http::response(['keys' => [[
                'kty' => 'RSA',
                'kid' => 'key-1',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $this->base64Url($details['n']),
                'e' => $this->base64Url($details['e']),
            ]]]),
        ]);
    }

    public function test_the_server_advertises_the_provider(): void
    {
        $this->getJson('/api/v1/server')
            ->assertJsonPath('auth_methods', ['password', 'oidc'])
            ->assertJsonPath('oidc.issuer', self::ISSUER)
            ->assertJsonPath('oidc.client_id', self::CLIENT_ID);
    }

    public function test_a_valid_token_creates_the_user_and_signs_in(): void
    {
        $token = $this->login($this->idToken())->assertOk()->assertJsonPath('user.username', 'lars')->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/user')->assertJsonPath('data.name', 'Lars');
        $this->login($this->idToken(['name' => 'Lars L.']))->assertOk();

        $this->assertSame('Lars L.', User::sole()->name);
        $this->assertSame('subject-1', User::sole()->oidc_sub);
    }

    public function test_the_first_sign_in_links_a_local_account_with_the_same_username(): void
    {
        $local = User::factory()->create(['username' => 'lars']);

        $this->login($this->idToken())->assertOk()->assertJsonPath('user.id', $local->id);

        $this->assertSame('subject-1', $local->refresh()->oidc_sub);
    }

    public function test_it_rejects_tokens_it_cannot_trust(): void
    {
        $foreignKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        $this->login($this->idToken(['aud' => 'another-client']))->assertUnprocessable();
        $this->login($this->idToken(['iss' => 'https://evil.test/']))->assertUnprocessable();
        $this->login($this->idToken(['exp' => time() - 60]))->assertUnprocessable();
        $this->login($this->idToken(key: $foreignKey))->assertUnprocessable();
        $this->login('not-a-token')->assertUnprocessable();

        $this->assertSame(0, User::count());
    }

    public function test_the_endpoint_does_not_exist_without_a_provider(): void
    {
        config(['services.oidc' => ['issuer' => null, 'client_id' => null]]);
        $this->app->forgetInstance(OidcVerifier::class);

        $this->login($this->idToken())->assertNotFound();
        $this->getJson('/api/v1/server')->assertJsonPath('auth_methods', ['password'])->assertJsonPath('oidc', null);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function login(string $idToken): TestResponse
    {
        return $this->postJson('/api/v1/auth/oidc', ['id_token' => $idToken, 'device_name' => 'iPhone']);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function idToken(array $claims = [], ?OpenSSLAsymmetricKey $key = null): string
    {
        return JWT::encode([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'subject-1',
            'exp' => time() + 300,
            'preferred_username' => 'lars',
            'name' => 'Lars',
            'email' => null,
            ...$claims,
        ], $key ?? $this->key, 'RS256', 'key-1');
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
