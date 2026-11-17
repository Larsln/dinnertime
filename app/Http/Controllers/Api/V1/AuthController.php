<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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

        return response()->json([
            'token' => $user->createToken($request->validated('device_name'))->plainTextToken,
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
