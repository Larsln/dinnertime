<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HouseholdSettings;
use App\Services\Planning\ShoppingListRefresher;
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
        return response()->json(['data' => $this->settings->toArray()]);
    }

    /**
     * Change the servings meals are planned for by default or whether the
     * shopping list notes what is at home already. Running lists are
     * refreshed, so that those written before carry the notes too.
     */
    public function update(Request $request, ShoppingListRefresher $refresher): JsonResponse
    {
        $data = $request->validate([
            'default_servings' => ['required_without:show_stocked_needs', 'integer', 'between:1,'.HouseholdSettings::MAX_SERVINGS],
            'show_stocked_needs' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['default_servings'])) {
            $this->settings->setDefaultServings($data['default_servings']);
        }

        if (isset($data['show_stocked_needs'])) {
            $this->settings->setShowsStockedNeeds($data['show_stocked_needs']);
            $refresher->running();
        }

        return $this->show();
    }
}
