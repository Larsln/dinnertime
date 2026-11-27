<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PortionVerdict;
use App\Http\Controllers\Controller;
use App\Http\Resources\MealPlanEntryResource;
use App\Models\MealPlanEntry;
use App\Services\Planning\ShoppingListRefresher;
use App\Services\Portions\PortionPlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PortionFeedbackController extends Controller
{
    /**
     * Say how the portion of a meal was for the signed-in user, or
     * withdraw that. Meals still ahead and their shopping lists follow.
     */
    public function update(Request $request, MealPlanEntry $entry, PortionPlanner $planner, ShoppingListRefresher $lists): MealPlanEntryResource
    {
        $data = $request->validate([
            'verdict' => ['present', 'nullable', Rule::enum(PortionVerdict::class)],
        ]);

        $userId = $request->user()->id;

        $days = DB::transaction(function () use ($data, $entry, $userId, $planner) {
            if ($data['verdict'] === null) {
                $entry->feedback()->where('user_id', $userId)->delete();
            } else {
                $entry->feedback()
                    ->firstOrNew(['user_id' => $userId], ['recipe_id' => $entry->recipe_id, 'served_factor' => $entry->factorOf($userId)])
                    ->fill(['verdict' => $data['verdict']])
                    ->save();
            }

            $entry->touch();

            return $planner->followFeedback();
        });

        $lists->afterChangesOn($days);

        return new MealPlanEntryResource($entry->load(['recipe.tags', 'feedback']));
    }
}
