<?php

namespace App\Services\Import;

use App\Enums\ImportStage;
use App\Models\Recipe;
use App\Services\Ai\GeminiException;
use Closure;

/**
 * Turns a link into a stored recipe.
 */
interface RecipeSourceImporter
{
    /**
     * @param  Closure(ImportStage): void  $report  Called whenever the import enters a new stage.
     *
     * @throws ImportFailedException
     * @throws GeminiException
     */
    public function import(string $url, Closure $report): Recipe;
}
