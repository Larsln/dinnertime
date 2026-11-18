<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeImportResource;
use App\Jobs\ImportRecipe;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Support\SourceLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Sleep;

class ImportController extends Controller
{
    private const int RECENT = 30;

    private const int WAIT_SECONDS = 240;

    private const int WAIT_INTERVAL_SECONDS = 2;

    /**
     * List the most recent imports.
     */
    public function index(): AnonymousResourceCollection
    {
        return RecipeImportResource::collection(
            RecipeImport::with('recipes.tags')->latest('id')->limit(self::RECENT)->get(),
        );
    }

    /**
     * Show an import, which clients poll until it is completed or failed.
     */
    public function show(RecipeImport $import): RecipeImportResource
    {
        return new RecipeImportResource($import->load('recipes.tags'));
    }

    /**
     * Answer as soon as the import is completed or has failed, or with its
     * current state after a few minutes. Lets a client learn the outcome
     * with a single request that may outlive its own process.
     */
    public function wait(RecipeImport $import): RecipeImportResource
    {
        $deadline = now()->addSeconds(self::WAIT_SECONDS);

        while ($import->isRunning() && now()->lt($deadline)) {
            Sleep::for(self::WAIT_INTERVAL_SECONDS)->seconds();
            $import->refresh();
        }

        return new RecipeImportResource($import->load('recipes.tags'));
    }

    /**
     * Queue the import of a shared link. A video that was imported before
     * is answered with its recipes right away.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
            'dish_count' => ['nullable', 'integer', 'between:1,'.RecipeImport::MAX_DISHES],
        ]);

        $existing = $this->findImported($data['url']);

        $import = RecipeImport::create([
            'url' => $data['url'],
            'user_id' => $request->user()->id,
            'dish_count' => $data['dish_count'] ?? null,
            'status' => $existing->isEmpty() ? ImportStatus::Pending : ImportStatus::Completed,
        ]);

        if ($existing->isEmpty()) {
            ImportRecipe::dispatch($import);
        } else {
            $import->attachRecipes($existing);
        }

        return (new RecipeImportResource($import->load('recipes.tags')))
            ->response()
            ->setStatusCode($existing->isEmpty() ? 202 : 200);
    }

    /**
     * Queue a failed import again.
     */
    public function retry(RecipeImport $import): JsonResponse
    {
        abort_unless($import->status === ImportStatus::Failed, 409, __('imports.not_failed'));

        $import->update(['status' => ImportStatus::Pending, 'error' => null]);
        ImportRecipe::dispatch($import);

        return (new RecipeImportResource($import->load('recipes.tags')))->response()->setStatusCode(202);
    }

    /**
     * Find the recipes of a link whose video id is part of the URL.
     *
     * @return Collection<int, Recipe>
     */
    private function findImported(string $url): Collection
    {
        $source = SourceLink::fromUrl($url);

        return $source->videoId === null ? new Collection : Recipe::fromVideo($source->platform, $source->videoId)->get();
    }
}
