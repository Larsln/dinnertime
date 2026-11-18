<?php

namespace App\Jobs;

use App\Enums\ImportError;
use App\Enums\ImportStage;
use App\Enums\ImportStatus;
use App\Enums\SourcePlatform;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiException;
use App\Services\Import\ImportFailedException;
use App\Services\Import\VideoRecipeImporter;
use App\Services\Import\WebRecipeImporter;
use App\Services\Recipes\RecipeWriter;
use App\Support\SourceLink;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Turns a shared link into recipes and records the outcome on the import.
 *
 * Instances without an AI key store an empty recipe that only carries the
 * link, so it can be filled in by hand.
 */
class ImportRecipe implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public RecipeImport $import)
    {
        $this->onQueue('imports');
    }

    public function handle(GeminiClient $gemini): void
    {
        $this->import->update(['status' => ImportStatus::Processing, 'error' => null]);

        try {
            $recipes = $gemini->isConfigured() ? $this->importWithAi() : $this->storePlaceholder();

            $this->import->attachRecipes($recipes);
            $this->import->update(['status' => ImportStatus::Completed, 'stage' => null]);
        } catch (ImportFailedException $exception) {
            $this->markFailed($exception->error);
        } catch (GeminiException) {
            $this->markFailed(ImportError::AiFailed);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed(ImportError::Unknown);
        }
    }

    /**
     * Record a timeout or crash of the worker as failed import.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed(ImportError::Unknown);
    }

    /**
     * @return Collection<int, Recipe>
     */
    private function importWithAi(): Collection
    {
        $isWebPage = SourceLink::fromUrl($this->import->url)->platform === SourcePlatform::Web;

        return app($isWebPage ? WebRecipeImporter::class : VideoRecipeImporter::class)->import(
            $this->import->url,
            $this->import->dish_count,
            function (ImportStage $stage): void {
                $this->import->update(['stage' => $stage]);
            },
        );
    }

    /**
     * @return Collection<int, Recipe>
     */
    private function storePlaceholder(): Collection
    {
        $existing = Recipe::where('source_url', $this->import->url)->orderBy('id')->get();

        return $existing->isNotEmpty() ? $existing : new Collection([
            app(RecipeWriter::class)->create([
                'title' => (string) parse_url($this->import->url, PHP_URL_HOST),
                'source_url' => $this->import->url,
                'source_platform' => SourceLink::fromUrl($this->import->url)->platform,
                'source_video_id' => SourceLink::fromUrl($this->import->url)->videoId,
            ]),
        ]);
    }

    private function markFailed(ImportError $error): void
    {
        $this->import->update(['status' => ImportStatus::Failed, 'stage' => null, 'error' => $error]);
    }
}
