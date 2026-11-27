<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealPlanEntryResource;
use App\Http\Resources\RecipeSummaryResource;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Services\Planning\ShoppingListRefresher;
use App\Services\Sides\MealSides;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MealSideController extends Controller
{
    /**
     * List the recipes that may be served with a dish, those served with
     * it most recently first.
     */
    public function index(Request $request, Recipe $recipe, MealSides $sides): AnonymousResourceCollection
    {
        return RecipeSummaryResource::collection(
            $sides->optionsFor($recipe)->withMarksOf($request->user())->with('tags')->get(),
        );
    }

    /**
     * Serve a side with a planned meal. Shopping lists covering the day
     * follow.
     */
    public function store(Request $request, MealPlanEntry $entry, MealSides $sides, ShoppingListRefresher $lists): JsonResponse
    {
        $data = $request->validate([
            'recipe_id' => ['required', 'integer', 'exists:recipes,id'],
        ]);

        $side = $sides->attach($entry, Recipe::findOrFail($data['recipe_id']));
        $lists->afterChangesOn([$side->date]);

        return (new MealPlanEntryResource($side->load(['recipe.tags', 'feedback'])))
            ->response()
            ->setStatusCode(201);
    }
}
