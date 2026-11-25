<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps a copy of the video a recipe was imported from, so the recipe
 * can still be watched after the platform took the video down.
 *
 * Recipes of several dishes shown in one video share its file, which is
 * deleted with the last of them.
 */
class VideoStore
{
    private const string DISK = 'public';

    private const string DIRECTORY = 'videos';

    /**
     * Whether copies are kept at all.
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.ytdlp.keep_videos');
    }

    /**
     * Keep the downloaded file for the recipes. Returns whether it was
     * kept: videos above the size limit are not.
     *
     * @param  iterable<Recipe>  $recipes
     */
    public function put(iterable $recipes, string $file): bool
    {
        if (! $this->isEnabled() || File::size($file) > config('services.ytdlp.max_video_mb') * 1024 * 1024) {
            return false;
        }

        $path = self::DIRECTORY.'/'.Str::lower(Str::random(20)).'.'.(File::extension($file) ?: 'mp4');
        Storage::disk(self::DISK)->writeStream($path, fopen($file, 'r'));

        foreach ($recipes as $recipe) {
            $previous = $recipe->video_path;
            $recipe->update(['video_path' => $path]);
            $this->deleteUnused($previous);
        }

        return true;
    }

    /**
     * Forget the video of a recipe that is about to be deleted.
     */
    public function delete(Recipe $recipe): void
    {
        $path = $recipe->video_path;
        $recipe->update(['video_path' => null]);
        $this->deleteUnused($path);
    }

    private function deleteUnused(?string $path): void
    {
        if ($path !== null && ! Recipe::where('video_path', $path)->exists()) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
