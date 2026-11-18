<?php

namespace App\Services\Import;

use App\Enums\ImportError;
use App\Enums\ImportStage;
use App\Models\Recipe;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\RecipeExtractionPrompt;
use App\Services\Recipes\CoverStore;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Imports the recipes of a web page.
 *
 * Structured recipe data and the visible text are handed to Gemini, which
 * brings them into the same form as recipes imported from videos.
 */
class WebRecipeImporter implements RecipeSourceImporter
{
    private const int MAX_TEXT_LENGTH = 30000;

    private const array IMAGE_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly RecipeDraftWriter $writer,
        private readonly ImportSettings $settings,
        private readonly CoverStore $covers,
    ) {}

    public function import(string $url, ?int $dishCount, Closure $report): Collection
    {
        $existing = Recipe::where('source_url', $url)->orderBy('id')->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $report(ImportStage::Downloading);
        $html = $this->fetch($url);

        $report(ImportStage::Analyzing);
        $prompt = new RecipeExtractionPrompt('the following web page', $this->pageContent($html), $dishCount);

        $answer = $this->gemini->generate(
            config('services.gemini.video_model'),
            [['text' => $prompt->text()]],
            $prompt->schema(),
        );

        if ($answer['recipes'] === []) {
            throw new ImportFailedException(ImportError::NoRecipeFound);
        }

        $report(ImportStage::Saving);
        $recipes = DB::transaction(fn () => array_map(
            fn (array $draft) => $this->writer->write($draft, $url),
            $answer['recipes'],
        ));
        $this->storeCover($recipes, $html);

        return new Collection($recipes);
    }

    /**
     * @throws ImportFailedException
     */
    private function fetch(string $url): string
    {
        try {
            return Http::withUserAgent($this->settings->userAgent())->timeout(30)->get($url)->throw()->body();
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
     * Save the page's preview image as cover of every recipe, if it names
     * a usable one.
     *
     * @param  list<Recipe>  $recipes
     */
    private function storeCover(array $recipes, string $html): void
    {
        if (! preg_match('~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']~i', $html, $match)) {
            return;
        }

        $image = rescue(fn () => Http::withUserAgent($this->settings->userAgent())->timeout(30)->get(html_entity_decode($match[1])), report: false);
        $extension = self::IMAGE_EXTENSIONS[Str::before((string) $image?->header('Content-Type'), ';')] ?? null;

        if (! $image?->successful() || $extension === null) {
            return;
        }

        foreach ($recipes as $recipe) {
            $this->covers->put($recipe, $image->body(), $extension);
        }
    }
}
