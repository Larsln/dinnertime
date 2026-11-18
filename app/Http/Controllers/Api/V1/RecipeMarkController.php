<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeSummaryResource;
use App\Models\Recipe;
use App\Models\RecipeMark;
use Illuminate\Http\Request;

class RecipeMarkController extends Controller
{
    /**
     * Rate a recipe from 0 (bad) to 10 (good), or withdraw the rating.
     */
    public function rate(Request $request, Recipe $recipe): RecipeSummaryResource
    {
        $data = $request->validate([
            'rating' => ['present', 'nullable', 'integer', 'between:'.RecipeMark::MIN_RATING.','.RecipeMark::MAX_RATING],
        ]);

        return $this->mark($request, $recipe, ['rating' => $data['rating']]);
    }

    /**
     * Mark a recipe as favorite or remove the mark.
     */
    public function favorite(Request $request, Recipe $recipe): RecipeSummaryResource
    {
        $data = $request->validate([
            'favorite' => ['required', 'boolean'],
        ]);

        return $this->mark($request, $recipe, ['is_favorite' => $data['favorite']]);
    }

    /**
     * Store part of the signed-in user's mark and answer with the recipe.
     *
     * @param  array<string, mixed>  $values
     */
    private function mark(Request $request, Recipe $recipe, array $values): RecipeSummaryResource
    {
        $recipe->marks()->updateOrCreate(['user_id' => $request->user()->id], $values);

        return new RecipeSummaryResource(
            Recipe::withMarksOf($request->user())->with('tags')->findOrFail($recipe->id),
        );
    }
}
