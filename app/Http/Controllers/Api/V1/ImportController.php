<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeImportResource;
use App\Jobs\ImportRecipe;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Support\SourceLink;
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
            RecipeImport::with('recipe.tags')->latest('id')->limit(self::RECENT)->get(),
        );
    }

    /**
     * Show an import, which clients poll until it is completed or failed.
     */
    public function show(RecipeImport $import): RecipeImportResource
    {
        return new RecipeImportResource($import->load('recipe.tags'));
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

        return new RecipeImportResource($import->load('recipe.tags'));
    }

    /**
     * Queue the import of a shared link. A video that was imported before
     * is answered with its recipe right away.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        $existing = $this->findImported($data['url']);

        $import = RecipeImport::create([
            'url' => $data['url'],
            'user_id' => $request->user()->id,
            'status' => $existing ? ImportStatus::Completed : ImportStatus::Pending,
            'recipe_id' => $existing?->id,
        ]);

        if ($existing === null) {
            ImportRecipe::dispatch($import);
        }

        return (new RecipeImportResource($import->load('recipe.tags')))
            ->response()
            ->setStatusCode($existing ? 200 : 202);
    }

    /**
     * Queue a failed import again.
     */
    public function retry(RecipeImport $import): JsonResponse
    {
        abort_unless($import->status === ImportStatus::Failed, 409, __('imports.not_failed'));

        $import->update(['status' => ImportStatus::Pending, 'error' => null]);
        ImportRecipe::dispatch($import);

        return (new RecipeImportResource($import))->response()->setStatusCode(202);
    }

    /**
     * Find the recipe of a link whose video id is part of the URL.
     */
    private function findImported(string $url): ?Recipe
    {
        $source = SourceLink::fromUrl($url);

        return $source->videoId === null ? null : Recipe::query()
            ->where('source_platform', $source->platform)
            ->where('source_video_id', $source->videoId)
            ->first();
    }
}
