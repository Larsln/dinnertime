<?php

namespace App\Enums;

/**
 * Reason a recipe import failed, shown to the user as a translated message.
 */
enum ImportError: string
{
    case DownloadFailed = 'download_failed';
    case NoRecipeFound = 'no_recipe_found';
    case AiFailed = 'ai_failed';
    case Unknown = 'unknown';

    public function message(): string
    {
        return __("imports.errors.{$this->value}");
    }
}
