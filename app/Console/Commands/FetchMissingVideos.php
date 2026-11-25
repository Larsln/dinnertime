<?php

namespace App\Console\Commands;

use App\Enums\SourcePlatform;
use App\Models\Recipe;
use App\Services\Import\ImportFailedException;
use App\Services\Import\VideoDownloader;
use App\Services\Recipes\VideoStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

#[Signature('recipes:fetch-missing-videos')]
#[Description('Keep a copy of the video of recipes imported before videos were kept')]
class FetchMissingVideos extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(VideoDownloader $downloader, VideoStore $videos): int
    {
        $groups = Recipe::whereNull('video_path')
            ->whereNotNull('source_url')
            ->whereIn('source_platform', [SourcePlatform::Tiktok, SourcePlatform::Instagram, SourcePlatform::Youtube])
            ->orderBy('id')
            ->get()
            ->groupBy('source_url');

        $fetched = 0;

        foreach ($groups as $url => $recipes) {
            $kept = $this->fetch((string) $url, $recipes, $downloader, $videos);
            $fetched += $kept ? 1 : 0;

            $this->components->twoColumnDetail($recipes->pluck('title')->join(', '), $kept ? 'video' : '<fg=yellow>not available</>');
        }

        $this->components->info("{$fetched} of {$groups->count()} videos were kept.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Recipe>  $recipes
     */
    private function fetch(string $url, Collection $recipes, VideoDownloader $downloader, VideoStore $videos): bool
    {
        $directory = storage_path('app/imports/'.Str::uuid());
        File::ensureDirectoryExists($directory);

        try {
            return $videos->put($recipes, $downloader->download($url, $directory));
        } catch (ImportFailedException) {
            return false;
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
