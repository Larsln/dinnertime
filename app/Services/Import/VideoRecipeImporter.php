<?php

namespace App\Services\Import;

use App\Enums\ImportError;
use App\Models\Recipe;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\RecipeExtractionPrompt;
use App\Support\SourceLink;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports a recipe from a TikTok, Instagram or YouTube video.
 *
 * The whole video is handed to Gemini together with its caption, so
 * recipes that are only shown or only written in the caption are found too.
 */
class VideoRecipeImporter implements RecipeSourceImporter
{
    private const int MAX_COVER_CANDIDATES = 4;

    public function __construct(
        private readonly VideoDownloader $downloader,
        private readonly GeminiClient $gemini,
        private readonly RecipeDraftWriter $writer,
        private readonly FrameExtractor $frames,
        private readonly CoverPicker $coverPicker,
    ) {}

    public function import(string $url): Recipe
    {
        $metadata = $this->downloader->metadata($url);
        $videoId = (string) $metadata['id'];
        $resolvedUrl = $metadata['webpage_url'] ?? $url;

        $existing = $this->findExisting($resolvedUrl, $videoId);

        if ($existing !== null) {
            return $existing;
        }

        $directory = storage_path('app/imports/'.Str::uuid());
        File::ensureDirectoryExists($directory);

        try {
            $video = $this->downloader->download($url, $directory);
            $draft = $this->extract($video, (string) ($metadata['description'] ?? ''));

            $recipe = $this->writer->write($draft, $resolvedUrl, $videoId);
            $this->storeCover($recipe, $video, $draft['cover_seconds'], $directory);

            return $recipe;
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * Share links differ on every share, so duplicates are found by the
     * id of the video.
     */
    private function findExisting(string $url, string $videoId): ?Recipe
    {
        return Recipe::query()
            ->where('source_platform', SourceLink::fromUrl($url)->platform)
            ->where('source_video_id', $videoId)
            ->first();
    }

    /**
     * Let Gemini watch the video and return the recipe it contains.
     *
     * @return array<string, mixed>
     */
    private function extract(string $video, string $caption): array
    {
        $file = $this->gemini->uploadFile($video, File::mimeType($video) ?: 'video/mp4');

        try {
            $prompt = new RecipeExtractionPrompt('the attached video and its caption', $caption);

            $draft = $this->gemini->generate(
                config('services.gemini.video_model'),
                [$file->asPart(), ['text' => $prompt->text()]],
                $prompt->schema(),
            );
        } finally {
            $this->gemini->deleteFile($file);
        }

        if (! $draft['is_recipe']) {
            throw new ImportFailedException(ImportError::NoRecipeFound);
        }

        return $draft;
    }

    /**
     * Take the sharpest frame around each suggested moment and keep the
     * one the AI likes best.
     *
     * @param  list<float|int>  $seconds
     */
    private function storeCover(Recipe $recipe, string $video, array $seconds, string $directory): void
    {
        $candidates = [];

        foreach (array_slice($seconds, 0, self::MAX_COVER_CANDIDATES) as $index => $second) {
            $path = "{$directory}/cover-{$index}.jpg";

            if ($this->frames->sharpestFrame($video, (float) $second, $path)) {
                $candidates[] = $path;
            }
        }

        if ($candidates === []) {
            return;
        }

        $target = "covers/{$recipe->id}.jpg";
        Storage::disk('public')->put($target, File::get($candidates[$this->coverPicker->pick($candidates)]));
        $recipe->update(['cover_image_path' => $target]);
    }
}
