<?php

namespace App\Services\Import;

use App\Enums\ImportError;
use App\Enums\ImportStage;
use App\Models\Recipe;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\RecipeExtractionPrompt;
use App\Services\Recipes\VideoStore;
use App\Support\SourceLink;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Imports the recipes of a TikTok, Instagram or YouTube video.
 *
 * The whole video is handed to Gemini together with its caption, so
 * recipes that are only shown or only written in the caption are found too.
 * Share links differ on every share, so a video imported before is
 * recognised by its id. A copy of the video is kept with the recipes,
 * because platforms take videos down.
 */
class VideoRecipeImporter implements RecipeSourceImporter
{
    public function __construct(
        private readonly VideoDownloader $downloader,
        private readonly GeminiClient $gemini,
        private readonly RecipeDraftWriter $writer,
        private readonly VideoCoverMaker $covers,
        private readonly VideoStore $videos,
    ) {}

    public function import(string $url, ?int $dishCount, Closure $report): Collection
    {
        $report(ImportStage::Downloading);
        $metadata = $this->downloader->metadata($url);
        $videoId = (string) $metadata['id'];
        $resolvedUrl = $metadata['webpage_url'] ?? $url;

        $existing = Recipe::fromVideo(SourceLink::fromUrl($resolvedUrl)->platform, $videoId)->get();

        if ($existing->isNotEmpty()) {
            $existing->whereNull('cover_image_path')->each(fn (Recipe $recipe) => $this->covers->fromPreview($recipe, $metadata['thumbnail'] ?? null));

            return $existing;
        }

        $directory = storage_path('app/imports/'.Str::uuid());
        File::ensureDirectoryExists($directory);

        try {
            $video = $this->downloader->download($url, $directory);

            $report(ImportStage::Analyzing);
            $drafts = $this->extract($video, (string) ($metadata['description'] ?? ''), $dishCount);

            $report(ImportStage::Saving);
            $recipes = DB::transaction(fn () => array_map(
                fn (array $draft) => $this->writer->write($draft, $resolvedUrl, $videoId),
                $drafts,
            ));

            foreach ($recipes as $index => $recipe) {
                $this->covers->fromVideo(
                    $recipe,
                    $video,
                    $drafts[$index]['cover_seconds'] ?? [],
                    isset($metadata['duration']) ? (float) $metadata['duration'] : null,
                    $metadata['thumbnail'] ?? null,
                    $directory,
                );
            }

            rescue(fn () => $this->videos->put($recipes, $video));

            return new Collection($recipes);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * Let Gemini watch the video and return the recipes it contains.
     *
     * @return non-empty-list<array<string, mixed>>
     */
    private function extract(string $video, string $caption, ?int $dishCount): array
    {
        $file = $this->gemini->uploadFile($video, File::mimeType($video) ?: 'video/mp4');

        try {
            $prompt = new RecipeExtractionPrompt('the attached video and its caption', $caption, $dishCount);

            $answer = $this->gemini->generate(
                config('services.gemini.video_model'),
                [$file->asPart(), ['text' => $prompt->text()]],
                $prompt->schema(),
            );
        } finally {
            $this->gemini->deleteFile($file);
        }

        if ($answer['recipes'] === []) {
            throw new ImportFailedException(ImportError::NoRecipeFound);
        }

        return $answer['recipes'];
    }
}
