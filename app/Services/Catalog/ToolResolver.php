<?php

namespace App\Services\Catalog;

use App\Models\Tool;
use Illuminate\Support\Str;

/**
 * Finds the catalog tool a name refers to, ignoring case, and adds the
 * tool to the catalog, starting with a capital letter, when it is new.
 */
class ToolResolver
{
    public function resolve(string $name): Tool
    {
        return Tool::whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])->first()
            ?? Tool::create(['name' => Str::ucfirst(trim($name))]);
    }
}
