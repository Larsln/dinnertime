<?php

namespace App\Console\Commands;

use App\Services\Mealie\CatalogImporter;
use App\Services\Mealie\RecipeImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mealie:import {--force : Overwrite records that were imported before}')]
#[Description('Import store sections, units, products and recipes from Mealie')]
class ImportMealie extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CatalogImporter $catalog, RecipeImporter $recipes): int
    {
        if (blank(config('services.mealie.token'))) {
            $this->components->error('MEALIE_TOKEN is not set.');

            return self::FAILURE;
        }

        $overwrite = (bool) $this->option('force');
        $counts = [...$catalog->import($overwrite), ...$recipes->import($overwrite)];

        $this->components->twoColumnDetail('Store sections', (string) $counts['sections']);
        $this->components->twoColumnDetail('Units', (string) $counts['units']);
        $this->components->twoColumnDetail('Products written', (string) $counts['products']);
        $this->components->twoColumnDetail('Recipes imported', (string) $counts['imported']);
        $this->components->twoColumnDetail('Recipes skipped', (string) $counts['skipped']);

        if ($recipes->unknownTags() !== []) {
            $this->components->warn('Ignored tags outside the fixed lists: '.implode(', ', $recipes->unknownTags()));
        }

        return self::SUCCESS;
    }
}
