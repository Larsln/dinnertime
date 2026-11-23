<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PeriodRequest;
use App\Models\MealPlanEntry;
use App\Services\Planning\PlanExplainer;
use App\Services\Planning\PlannedMeal;
use App\Services\Planning\ShoppingForecaster;
use Illuminate\Http\JsonResponse;

class MealPlanReasonController extends Controller
{
    /**
     * Tell for every entry of a period why it goes with the others: the
     * packages it shares, what it uses from the pantry and what speaks
     * for the recipe. Computed from the plan as it is now, so the reasons
     * follow every change.
     */
    public function __invoke(PeriodRequest $request, PlanExplainer $explainer): JsonResponse
    {
        $entries = MealPlanEntry::query()
            ->with(array_map(fn (string $relation) => "recipe.{$relation}", ShoppingForecaster::RECIPE_RELATIONS))
            ->whereBetween('date', [$request->from(), $request->to()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $reasons = $explainer->explain($entries->map(PlannedMeal::fromEntry(...))->all(), $request->from(), $request->user());

        return response()->json([
            'data' => $entries->values()->map(fn (MealPlanEntry $entry, int $index) => [
                'entry_id' => $entry->id,
                'reasons' => $reasons[$index],
            ]),
        ]);
    }
}
