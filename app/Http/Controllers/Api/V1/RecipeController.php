<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Course;
use App\Enums\TagType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeSummaryResource;
use App\Models\Recipe;
use App\Models\Tag;
use App\Services\Recipes\CoverStore;
use App\Services\Recipes\RecipeWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class RecipeController extends Controller
{
    private const array DETAIL_RELATIONS = ['ingredients.product', 'ingredients.unit', 'steps', 'tags', 'tools'];

    /**
     * List recipes, optionally filtered by title, tags, courses and
     * favorites. Courses widen the result and so do meals: a recipe has to
     * be of one of the courses and suit one of the meals. The other tags
     * narrow it: a recipe has to carry all of them.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'integer', 'exists:tags,id'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'course' => ['nullable', Rule::enum(Course::class)],
            'courses' => ['nullable', 'array', 'max:20'],
            'courses.*' => [Rule::enum(Course::class)],
            'favorite' => ['nullable', 'boolean'],
        ]);

        $tags = Tag::findMany($filters['tags'] ?? [])->groupBy(fn (Tag $tag) => $tag->type->value);

        $user = $request->user();

        $recipes = Recipe::query()
            ->withMarksOf($user)
            ->with('tags')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->whereLike('title', "%{$search}%"))
            ->when($filters['tag'] ?? null, fn (Builder $query, int $tagId) => $query
                ->whereRelation('tags', 'tags.id', $tagId))
            ->when($filters['course'] ?? null, fn (Builder $query, string $course) => $query
                ->where('course', $course))
            ->when($filters['courses'] ?? null, fn (Builder $query, array $courses) => $query
                ->whereIn('course', $courses))
            ->when($tags->get(TagType::Meal->value), fn (Builder $query, Collection $meals) => $query
                ->whereHas('tags', fn (Builder $query) => $query->whereKey($meals->modelKeys())))
            ->when($request->boolean('favorite'), fn (Builder $query) => $query
                ->whereHas('marks', fn (Builder $query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_favorite', true)))
            ->orderBy('title');

        foreach ($tags->get(TagType::Tag->value, []) as $tag) {
            $recipes->whereRelation('tags', 'tags.id', $tag->id);
        }

        return RecipeSummaryResource::collection($recipes->paginate(50));
    }

    /**
     * Show a recipe with ingredients, steps, tags and tools.
     */
    public function show(Request $request, Recipe $recipe): RecipeResource
    {
        return $this->detail($request, $recipe);
    }

    /**
     * Create a recipe by hand.
     */
    public function store(RecipeRequest $request, RecipeWriter $writer): JsonResponse
    {
        return $this->detail($request, $writer->create($this->recipeData($request)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Change a recipe. Assigning tags by hand protects them from the AI.
     */
    public function update(RecipeRequest $request, Recipe $recipe, RecipeWriter $writer): RecipeResource
    {
        return $this->detail($request, $writer->update($recipe, $this->recipeData($request)));
    }

    /**
     * The recipe with everything needed to cook it and the user's marks.
     */
    private function detail(Request $request, Recipe $recipe): RecipeResource
    {
        return new RecipeResource(
            Recipe::withMarksOf($request->user())->with(self::DETAIL_RELATIONS)->findOrFail($recipe->id),
        );
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
    public function cover(Request $request, Recipe $recipe, CoverStore $covers): RecipeResource
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $image = $request->file('image');
        $covers->put($recipe, $image->getContent(), $image->extension());

        return $this->detail($request, $recipe);
    }

    /**
     * Delete a recipe together with its cover image.
     */
    public function destroy(Recipe $recipe, CoverStore $covers): Response
    {
        $covers->delete($recipe);
        $recipe->delete();

        return response()->noContent();
    }
}
