<?php

namespace App\Services\Ai;

/**
 * File uploaded to Gemini that prompts can refer to.
 */
final readonly class GeminiFile
{
    public function __construct(
        public string $name,
        public string $uri,
        public string $mimeType,
    ) {}

    /**
     * Content part that attaches the file to a prompt.
     *
     * @return array{fileData: array{mimeType: string, fileUri: string}}
     */
    public function asPart(): array
    {
        return ['fileData' => ['mimeType' => $this->mimeType, 'fileUri' => $this->uri]];
    }
}
