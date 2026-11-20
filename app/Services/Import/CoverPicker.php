<?php

namespace App\Services\Import;

use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Lets the AI choose the most appetising of several candidate cover images.
 */
class CoverPicker
{
    private const string PROMPT = <<<'PROMPT'
        These are candidate cover images for a recipe, numbered from 0 in the order given.
        Choose the image that shows the finished dish most appetisingly: sharp, well lit and close.
        Text overlays and people in the image count against it but do not rule it out.
        PROMPT;

    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * Index of the best image. Falls back to the first one when there is
     * no choice to make or the AI gives no usable answer in time.
     *
     * @param  list<string>  $imagePaths  JPEG files.
     */
    public function pick(array $imagePaths): int
    {
        if (count($imagePaths) < 2) {
            return 0;
        }

        $images = array_map(fn (string $path) => [
            'inlineData' => ['mimeType' => 'image/jpeg', 'data' => base64_encode(file_get_contents($path))],
        ], $imagePaths);

        try {
            $answer = $this->gemini->generate(
                config('services.gemini.text_model'),
                [['text' => self::PROMPT], ...$images],
                ['type' => 'OBJECT', 'properties' => ['index' => ['type' => 'INTEGER']], 'required' => ['index']],
            );
        } catch (GeminiException|ConnectionException|RequestException) {
            return 0;
        }

        return isset($imagePaths[$answer['index']]) ? $answer['index'] : 0;
    }
}
