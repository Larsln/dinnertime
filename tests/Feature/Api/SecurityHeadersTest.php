<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_the_web_client_may_only_load_scripts_from_the_server(): void
    {
        config(['services.oidc.issuer' => 'https://auth.example.org:8443/application/o/dinnertime/']);

        $policy = $this->get('/recipes')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self';", $policy);
        $this->assertStringContainsString("connect-src 'self' https://auth.example.org:8443;", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
    }

    public function test_without_a_provider_connections_stay_on_the_server(): void
    {
        config(['services.oidc.issuer' => null]);

        $policy = $this->getJson('/api/v1/server')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("connect-src 'self';", $policy);
    }

    public function test_https_requests_ask_browsers_to_stay_on_https(): void
    {
        $this->get('https://localhost/recipes')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/recipes')->assertHeaderMissing('Strict-Transport-Security');
    }
}
