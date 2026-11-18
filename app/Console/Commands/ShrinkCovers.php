<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Services\Recipes\CoverStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('covers:shrink')]
#[Description('Store covers that have no small version again, under a name of their own and with a small version')]
class ShrinkCovers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CoverStore $covers): int
    {
        $recipes = Recipe::whereNotNull('cover_image_path')->whereNull('cover_thumb_path')->get();

        foreach ($recipes as $recipe) {
            $path = $recipe->cover_image_path;

            if (Storage::disk('public')->exists($path)) {
                $covers->put($recipe, Storage::disk('public')->get($path), pathinfo($path, PATHINFO_EXTENSION));
            }
        }

        $this->components->info("Stored {$recipes->count()} covers again.");

        return self::SUCCESS;
    }
}
