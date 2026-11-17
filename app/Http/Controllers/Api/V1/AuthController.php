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
        $user = User::firstWhere('username', $request->validated('username'));

        if ($user?->password === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        return $this->tokenResponse($user, $request->validated('device_name'));
    }

    /**
     * Exchange an ID token of the configured OIDC provider for an API token.
     *
     * The account is found by its subject. On first sign-in it is linked to
     * a local account with the same username or created.
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
            ?? User::whereNull('oidc_sub')->firstWhere('username', $identity->username)
            ?? new User(['username' => $identity->username]);

        $user->fill(['oidc_sub' => $identity->subject, 'name' => $identity->name, 'email' => $identity->email])->save();

        return $this->tokenResponse($user, $data['device_name']);
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
