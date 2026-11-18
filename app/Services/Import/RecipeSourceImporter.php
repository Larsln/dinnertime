<?php

namespace App\Services\Import;

use App\Enums\ImportStage;
use App\Models\Recipe;
use App\Services\Ai\GeminiException;
use Closure;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turns a link into stored recipes, one per dish it shows.
 */
interface RecipeSourceImporter
{
    /**
     * @param  ?int  $dishCount  Number of dishes the person sharing the link expects, null to let the AI decide.
     * @param  Closure(ImportStage): void  $report  Called whenever the import enters a new stage.
     * @return Collection<int, Recipe>
     *
     * @throws ImportFailedException
     * @throws GeminiException
     */
    public function import(string $url, ?int $dishCount, Closure $report): Collection;
}
