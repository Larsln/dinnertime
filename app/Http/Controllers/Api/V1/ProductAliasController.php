<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductAlias;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductAliasController extends Controller
{
    /**
     * Add an alternative name to a product.
     */
    public function store(Request $request, Product $product): ProductResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:product_aliases,name', 'unique:products,name'],
        ]);

        $product->aliases()->create($data);

        return new ProductResource($product->load(['storeSection', 'aliases']));
    }

    /**
     * Remove an alternative name.
     */
    public function destroy(ProductAlias $alias): Response
    {
        $alias->delete();

        return response()->noContent();
    }
}
