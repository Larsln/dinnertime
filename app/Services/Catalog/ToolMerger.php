<?php

namespace App\Services\Catalog;

use App\Models\Tool;
use Illuminate\Support\Facades\DB;

/**
 * Merges a duplicate tool into another one. Recipes that needed the
 * duplicate need the remaining tool afterwards.
 */
class ToolMerger
{
    public function merge(Tool $duplicate, Tool $target): void
    {
        DB::transaction(function () use ($duplicate, $target) {
            $target->recipes()->syncWithoutDetaching($duplicate->recipes()->pluck('recipes.id'));
            $duplicate->delete();
        });
    }
}
