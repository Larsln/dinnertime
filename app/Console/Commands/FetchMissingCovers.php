<?php

namespace App\Console\Commands;

use App\Enums\SourcePlatform;
use App\Models\Recipe;
use App\Services\Import\ImportFailedException;
use App\Services\Import\VideoCoverMaker;
use App\Services\Import\VideoDownloader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recipes:fetch-missing-covers')]
#[Description('Give recipes imported from videos without a cover the preview image of the platform')]
class FetchMissingCovers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(VideoDownloader $downloader, VideoCoverMaker $covers): int
    {
        $recipes = Recipe::whereNull('cover_image_path')
            ->whereNotNull('source_url')
            ->whereIn('source_platform', [SourcePlatform::Tiktok, SourcePlatform::Instagram, SourcePlatform::Youtube])
            ->orderBy('id')
            ->get();

        $fetched = 0;

        foreach ($recipes as $recipe) {
            try {
                $preview = $downloader->metadata((string) $recipe->source_url)['thumbnail'] ?? null;
            } catch (ImportFailedException) {
                $preview = null;
            }

            if ($covers->fromPreview($recipe, $preview)) {
                $fetched++;
                $this->components->twoColumnDetail($recipe->title, 'cover');
            } else {
                $this->components->twoColumnDetail($recipe->title, '<fg=yellow>no preview image</>');
            }
        }

        $this->components->info("{$fetched} of {$recipes->count()} recipes got a cover.");

        return self::SUCCESS;
    }
}
