<?php

namespace App\Services\Import;

use App\Enums\ImportError;
use App\Models\Recipe;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\RecipeExtractionPrompt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports a recipe from a web page.
 *
 * Structured recipe data and the visible text are handed to Gemini, which
 * brings them into the same form as recipes imported from videos.
 */
class WebRecipeImporter implements RecipeSourceImporter
{
    private const string USER_AGENT = 'Mozilla/5.0 (compatible; MealplannerBot/1.0)';

    private const int MAX_TEXT_LENGTH = 30000;

    private const array IMAGE_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly RecipeDraftWriter $writer,
    ) {}

    public function import(string $url): Recipe
    {
        $existing = Recipe::firstWhere('source_url', $url);

        if ($existing !== null) {
            return $existing;
        }

        $html = $this->fetch($url);
        $prompt = new RecipeExtractionPrompt('the following web page', $this->pageContent($html));

        $draft = $this->gemini->generate(
            config('services.gemini.video_model'),
            [['text' => $prompt->text()]],
            $prompt->schema(),
        );

        if (! $draft['is_recipe']) {
            throw new ImportFailedException(ImportError::NoRecipeFound);
        }

        $recipe = $this->writer->write($draft, $url);
        $this->storeCover($recipe, $html);

        return $recipe;
    }

    /**
     * @throws ImportFailedException
     */
    private function fetch(string $url): string
    {
        try {
            return Http::withUserAgent(self::USER_AGENT)->timeout(30)->get($url)->throw()->body();
        } catch (RequestException|ConnectionException $exception) {
            throw new ImportFailedException(ImportError::DownloadFailed, $exception->getMessage());
        }
    }

    /**
     * Structured data followed by the visible text of the page, shortened
     * to what the model needs.
     */
    private function pageContent(string $html): string
    {
        preg_match_all('~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~is', $html, $structured);

        $text = preg_replace(['~<(script|style|noscript)\b.*?</\1>~is', '~\s+~'], ['', ' '], $html);
        $text = html_entity_decode(strip_tags((string) $text));

        return Str::limit(implode("\n", $structured[1])."\n\n".trim($text), self::MAX_TEXT_LENGTH, '');
    }

    /**
     * Save the page's preview image as cover, if it names a usable one.
     */
    private function storeCover(Recipe $recipe, string $html): void
    {
        if (! preg_match('~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']~i', $html, $match)) {
            return;
        }

        $image = rescue(fn () => Http::withUserAgent(self::USER_AGENT)->timeout(30)->get(html_entity_decode($match[1])), report: false);
        $extension = self::IMAGE_EXTENSIONS[Str::before((string) $image?->header('Content-Type'), ';')] ?? null;

        if ($image?->successful() && $extension !== null) {
            $target = "covers/{$recipe->id}.{$extension}";
            Storage::disk('public')->put($target, $image->body());
            $recipe->update(['cover_image_path' => $target]);
        }
    }
}
