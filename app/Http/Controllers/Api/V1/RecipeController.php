<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeSummaryResource;
use App\Models\Recipe;
use App\Services\Recipes\RecipeWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class RecipeController extends Controller
{
    private const array DETAIL_RELATIONS = ['ingredients.product', 'ingredients.unit', 'steps', 'tags', 'tools'];

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
        return new RecipeResource($recipe->load(self::DETAIL_RELATIONS));
    }

    /**
     * Create a recipe by hand.
     */
    public function store(RecipeRequest $request, RecipeWriter $writer): RecipeResource
    {
        return new RecipeResource($writer->create($this->recipeData($request))->load(self::DETAIL_RELATIONS));
    }

    /**
     * Change a recipe. Assigning tags by hand protects them from the AI.
     */
    public function update(RecipeRequest $request, Recipe $recipe, RecipeWriter $writer): RecipeResource
    {
        return new RecipeResource($writer->update($recipe, $this->recipeData($request))->load(self::DETAIL_RELATIONS));
    }

    /**
     * Validated recipe data. Tags assigned by a user are locked so the AI
     * does not overwrite them later.
     *
     * @return array<string, mixed>
     */
    private function recipeData(RecipeRequest $request): array
    {
        return [
            ...$request->validated(),
            ...($request->has('tag_ids') ? ['meal_tags_locked' => true] : []),
        ];
    }

    /**
     * Replace the cover image.
     */
    public function cover(Request $request, Recipe $recipe): RecipeResource
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $previous = $recipe->cover_image_path;
        $recipe->update(['cover_image_path' => $request->file('image')->store('covers', 'public')]);

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        return new RecipeResource($recipe->load(self::DETAIL_RELATIONS));
    }

    /**
     * Delete a recipe together with its cover image.
     */
    public function destroy(Recipe $recipe): Response
    {
        if ($recipe->cover_image_path !== null) {
            Storage::disk('public')->delete($recipe->cover_image_path);
        }

        $recipe->delete();

        return response()->noContent();
    }
}
