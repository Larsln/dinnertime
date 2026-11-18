<?php

namespace App\Services\Catalog;

use App\Enums\ReviewStatus;
use App\Models\Product;

/**
 * Finds the product an ingredient name refers to.
 *
 * Only names, plural names and aliases that match exactly, ignoring case,
 * count. Anything else becomes a new suggested product, so different
 * products are never merged silently.
 */
class ProductResolver
{
    public function resolve(string $name): Product
    {
        return $this->find($name) ?? Product::create(['name' => trim($name), 'status' => ReviewStatus::Suggested]);
    }

    /**
     * The product known under the name, if any.
     */
    public function find(string $name): ?Product
    {
        $needle = mb_strtolower(trim($name));

        return Product::query()
            ->whereRaw('lower(name) = ?', [$needle])
            ->orWhereRaw('lower(plural_name) = ?', [$needle])
            ->orWhereHas('aliases', fn ($query) => $query->whereRaw('lower(name) = ?', [$needle]))
            ->first();
    }
}
