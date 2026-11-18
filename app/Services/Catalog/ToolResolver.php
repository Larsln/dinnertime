<?php

namespace App\Services\Catalog;

use App\Models\Tool;

/**
 * Finds the catalog tool a name refers to, ignoring case, and adds the
 * tool to the catalog when it is new.
 */
class ToolResolver
{
    public function resolve(string $name): Tool
    {
        return Tool::whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])->first()
            ?? Tool::create(['name' => trim($name)]);
    }
}
