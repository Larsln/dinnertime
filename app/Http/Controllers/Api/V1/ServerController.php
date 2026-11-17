<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ServerController extends Controller
{
    /**
     * Describe the server so clients can verify the URL and offer a sign-in.
     *
     * The content locale is the language the household writes its recipes
     * in, independent of the language the interface is requested in.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'content_locale' => config('app.content_locale'),
            'supported_locales' => config('app.supported_locales'),
            'auth_methods' => ['password'],
        ]);
    }
}
