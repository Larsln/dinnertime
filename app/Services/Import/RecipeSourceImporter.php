<?php

namespace App\Services\Import;

use App\Models\Recipe;
use App\Services\Ai\GeminiException;

/**
 * Turns a link into a stored recipe.
 */
interface RecipeSourceImporter
{
    /**
     * @throws ImportFailedException
     * @throws GeminiException
     */
    public function import(string $url): Recipe;
}
