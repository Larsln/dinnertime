<?php

namespace App\Services\Mealie;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the parts of the Mealie API the migration needs.
 */
final readonly class MealieClient
{
    public function __construct(
        private string $baseUrl,
        private string $token,
    ) {}

    /**
     * Shopping labels, which Mealie uses as supermarket sections.
     *
     * @return list<array<string, mixed>>
     */
    public function labels(): array
    {
        return $this->all('/api/groups/labels');
    }

    /**
     * Foods including their aliases and label.
     *
     * @return list<array<string, mixed>>
     */
    public function foods(): array
    {
        return $this->all('/api/foods');
    }

    /**
     * Units of measurement.
     *
     * @return list<array<string, mixed>>
     */
    public function units(): array
    {
        return $this->all('/api/units');
    }

    /**
     * Summaries of all recipes, without ingredients and steps.
     *
     * @return list<array<string, mixed>>
     */
    public function recipeSummaries(): array
    {
        return $this->all('/api/recipes');
    }

    /**
     * Full recipe including ingredients, steps and organizers.
     *
     * @return array<string, mixed>
     */
    public function recipe(string $slug): array
    {
        return $this->request()->get("/api/recipes/{$slug}")->throw()->json();
    }

    /**
     * Binary content of the original cover image, if the recipe has one.
     */
    public function recipeImage(string $recipeId): ?string
    {
        $response = $this->request()->get("/api/media/recipes/{$recipeId}/images/original.webp");

        return $response->successful() ? $response->body() : null;
    }

    /**
     * Fetch every item of a paginated collection.
     *
     * @return list<array<string, mixed>>
     */
    private function all(string $path): array
    {
        return $this->request()->get($path, ['perPage' => -1])->throw()->json('items');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)->withToken($this->token)->acceptJson()->timeout(30);
    }
}
