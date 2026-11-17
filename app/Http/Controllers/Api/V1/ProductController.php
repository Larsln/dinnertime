<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * List products, optionally filtered by name or alias.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $products = Product::query()
            ->with(['storeSection', 'aliases'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where(fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereRelation('aliases', fn (Builder $query) => $query
                        ->whereLike('name', "%{$search}%"))))
            ->orderBy('name')
            ->paginate(100);

        return ProductResource::collection($products);
    }

    /**
     * Show a product with its aliases, conversions and package sizes.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load([
            'storeSection',
            'aliases',
            'conversions.unit',
            'packages',
        ]));
    }
}
