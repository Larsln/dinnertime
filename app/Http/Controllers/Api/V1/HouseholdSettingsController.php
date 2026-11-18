<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HouseholdSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdSettingsController extends Controller
{
    public function __construct(private readonly HouseholdSettings $settings) {}

    /**
     * Show the settings of the household.
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'default_servings' => $this->settings->defaultServings(),
            'currency' => $this->settings->currency(),
        ]]);
    }

    /**
     * Change the number of servings meals are planned for by default.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_servings' => ['required', 'integer', 'between:1,'.HouseholdSettings::MAX_SERVINGS],
        ]);

        $this->settings->setDefaultServings($data['default_servings']);

        return $this->show();
    }
}
