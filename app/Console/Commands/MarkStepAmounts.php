<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiException;
use App\Services\Ai\StepAmountMarker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('recipes:mark-step-amounts')]
#[Description('Let the AI mark amounts in the steps of recipes that have none marked, so they scale with the servings')]
class MarkStepAmounts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GeminiClient $gemini, StepAmountMarker $marker): int
    {
        if (! $gemini->isConfigured()) {
            $this->components->error('GEMINI_API_KEY is not set.');

            return self::FAILURE;
        }

        $recipes = Recipe::query()
            ->whereHas('steps')
            ->whereDoesntHave('steps', fn (Builder $query) => $query->where('text', '~', '\{\d'))
            ->get();
        $changed = 0;

        $this->withProgressBar($recipes, function (Recipe $recipe) use ($marker, &$changed) {
            try {
                $changed += $marker->mark($recipe);
            } catch (GeminiException $exception) {
                $this->components->warn("{$recipe->title}: {$exception->getMessage()}");
            }
        });

        $this->newLine(2);
        $this->components->info("Marked amounts in {$changed} steps of {$recipes->count()} recipes.");

        return self::SUCCESS;
    }
}
