<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\InvalidIdTokenException;
use App\Services\Auth\OidcVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Exchange username and password for an API token.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        abort_unless(config('services.password_login'), 404);

        $user = User::firstWhere('username', $request->validated('username'));

        if ($user?->password === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        return $this->tokenResponse($user, $request->validated('device_name'));
    }

    /**
     * Exchange an ID token of the configured OIDC provider for an API token.
     *
     * The account is found by its subject. On first sign-in it takes over the
     * local account with the same username if an administrator released it
     * with `user:link-oidc`, and is created otherwise. A username that a
     * local account already uses is refused, so nobody signs in as someone
     * else by choosing their name at the provider.
     *
     * @throws ValidationException
     */
    public function oidc(Request $request, OidcVerifier $verifier): JsonResponse
    {
        abort_unless($verifier->isConfigured(), 404);

        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $identity = $verifier->verify($data['id_token']);
        } catch (InvalidIdTokenException) {
            throw ValidationException::withMessages(['id_token' => __('auth.failed_oidc')]);
        }

        $user = User::firstWhere('oidc_sub', $identity->subject)
            ?? User::whereNull('oidc_sub')->where('oidc_link_allowed', true)->firstWhere('username', $identity->username)
            ?? $this->newOidcUser($identity->username);

        $user->fill([
            'oidc_sub' => $identity->subject,
            'oidc_link_allowed' => false,
            'name' => $identity->name,
            'email' => $identity->email,
        ])->save();

        return $this->tokenResponse($user, $data['device_name']);
    }

    /**
     * A new account for a provider identity whose username is still free.
     *
     * @throws ValidationException
     */
    private function newOidcUser(string $username): User
    {
        if (User::where('username', $username)->exists()) {
            throw ValidationException::withMessages(['id_token' => __('auth.username_taken_oidc', ['username' => $username])]);
        }

        return new User(['username' => $username]);
    }

    private function tokenResponse(User $user, string $deviceName): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Revoke the token the request was made with.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Show the signed-in user.
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
