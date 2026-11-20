<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

/**
 * Downloads preview images that pages and platforms point to.
 */
class ImageDownloader
{
    private const array EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly PublicFetcher $fetcher) {}

    /**
     * The image behind the link with the file extension its type calls
     * for, or null when it cannot be fetched or is no JPEG, PNG or WebP.
     *
     * @return array{contents: string, extension: string}|null
     */
    public function download(string $url): ?array
    {
        $response = rescue(fn () => $this->fetcher->get($url), report: false);
        $extension = self::EXTENSIONS[Str::before((string) $response?->header('Content-Type'), ';')] ?? null;

        if (! $response?->successful() || $extension === null || $response->body() === '') {
            return null;
        }

        return ['contents' => $response->body(), 'extension' => $extension];
    }
}
