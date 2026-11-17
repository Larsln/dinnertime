<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeSummaryResource;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecipeController extends Controller
{
    /**
     * List recipes, optionally filtered by title and tag.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'integer', 'exists:tags,id'],
        ]);

        $recipes = Recipe::query()
            ->with('tags')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->whereLike('title', "%{$search}%"))
            ->when($filters['tag'] ?? null, fn (Builder $query, int $tagId) => $query
                ->whereRelation('tags', 'tags.id', $tagId))
            ->orderBy('title')
            ->paginate(50);

        return RecipeSummaryResource::collection($recipes);
    }

    /**
     * Show a recipe with ingredients, steps, tags and tools.
     */
    public function show(Recipe $recipe): RecipeResource
    {
        return new RecipeResource($recipe->load([
            'ingredients.product',
            'ingredients.unit',
            'steps',
            'tags',
            'tools',
        ]));
    }
}
