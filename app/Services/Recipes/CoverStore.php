<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps the cover image of a recipe together with a small version for
 * lists.
 *
 * Every image gets a file name of its own, so its address never shows
 * another image and clients can keep what they downloaded for good.
 */
class CoverStore
{
    private const string DISK = 'public';

    private const string DIRECTORY = 'covers';

    private const int THUMB_WIDTH = 480;

    /**
     * Replace the cover of the recipe by the given image.
     */
    public function put(Recipe $recipe, string $contents, string $extension): void
    {
        $previous = [$recipe->cover_image_path, $recipe->cover_thumb_path];
        $name = self::DIRECTORY."/{$recipe->id}-".Str::lower(Str::random(10));
        $path = "{$name}.{$extension}";

        Storage::disk(self::DISK)->put($path, $contents);
        $recipe->update(['cover_image_path' => $path, 'cover_thumb_path' => $this->shrink($path, "{$name}-small.jpg")]);

        Storage::disk(self::DISK)->delete(array_filter($previous));
    }

    /**
     * Delete the images of the recipe.
     */
    public function delete(Recipe $recipe): void
    {
        Storage::disk(self::DISK)->delete(array_filter([$recipe->cover_image_path, $recipe->cover_thumb_path]));
    }

    /**
     * Write a small version of the image and return its path, or null
     * when the image cannot be converted.
     */
    private function shrink(string $path, string $target): ?string
    {
        $disk = Storage::disk(self::DISK);
        $result = Process::timeout(60)->run([
            'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $disk->path($path),
            '-vf', 'scale='.self::THUMB_WIDTH.':-2',
            '-frames:v', '1', '-q:v', '5',
            $disk->path($target),
        ]);

        return $result->successful() && $disk->exists($target) ? $target : null;
    }
}
