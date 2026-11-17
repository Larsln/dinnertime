<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_describes_the_server_without_authentication(): void
    {
        config(['app.content_locale' => 'de']);

        $this->getJson('/api/v1/server', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('content_locale', 'de')
            ->assertJsonPath('supported_locales', ['de', 'en'])
            ->assertJsonPath('auth_methods', ['password']);
    }

    public function test_it_issues_a_token_that_grants_access(): void
    {
        User::factory()->create(['username' => 'lars', 'password' => 'correct-horse']);

        $token = $this->postJson('/api/v1/auth/login', [
            'username' => 'lars',
            'password' => 'correct-horse',
            'device_name' => 'iPhone',
        ])->assertOk()->assertJsonPath('user.username', 'lars')->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.username', 'lars')
            ->assertJsonMissingPath('data.password');
    }

    public function test_it_rejects_wrong_credentials(): void
    {
        User::factory()->create(['username' => 'lars', 'password' => 'correct-horse']);
        User::factory()->create(['username' => 'sso-only', 'password' => null]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'lars',
            'password' => 'wrong',
            'device_name' => 'iPhone',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/login', [
            'username' => 'sso-only',
            'password' => '',
            'device_name' => 'iPhone',
        ])->assertUnprocessable();
    }

    public function test_it_revokes_the_token_on_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_it_requires_authentication_for_household_data(): void
    {
        $this->getJson('/api/v1/recipes')->assertUnauthorized();
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_the_create_command_adds_a_user_who_can_sign_in(): void
    {
        $this->artisan('user:create pauline --password=long-enough-secret')->assertSuccessful();
        $this->artisan('user:create pauline --password=long-enough-secret')->assertFailed();
        $this->artisan('user:create short --password=abc')->assertFailed();

        $this->postJson('/api/v1/auth/login', [
            'username' => 'pauline',
            'password' => 'long-enough-secret',
            'device_name' => 'Web',
        ])->assertOk();
        $this->assertSame(1, User::count());
    }
}
