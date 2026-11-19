<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Jobs\EnrichProduct;
use App\Models\Product;
use App\Services\Catalog\ProductMerger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private const array DETAIL_RELATIONS = ['storeSection', 'aliases', 'conversions.unit', 'offers.store'];

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
     * List products with details or conversions awaiting review.
     */
    public function review(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->awaitingReview()
            ->with(self::DETAIL_RELATIONS)
            ->withCount('ingredients')
            ->orderBy('name')
            ->paginate(50);

        return ProductResource::collection($products);
    }

    /**
     * Show a product with its aliases, conversions and package sizes.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(self::DETAIL_RELATIONS)->loadCount('ingredients'));
    }

    /**
     * Create a product by hand and ask the AI for details and conversions.
     */
    public function store(ProductRequest $request): ProductResource
    {
        $product = Product::create([...$request->validated(), 'status' => ReviewStatus::Confirmed]);

        EnrichProduct::dispatch($product);

        return new ProductResource($product->refresh()->load(self::DETAIL_RELATIONS));
    }

    /**
     * Change the details of a product.
     */
    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->load(self::DETAIL_RELATIONS));
    }

    /**
     * Confirm a product together with all of its conversions.
     */
    public function confirm(Product $product): ProductResource
    {
        DB::transaction(function () use ($product) {
            $product->update(['status' => ReviewStatus::Confirmed]);
            $product->conversions()->update(['status' => ReviewStatus::Confirmed]);
        });

        return new ProductResource($product->load(self::DETAIL_RELATIONS));
    }

    /**
     * Merge the product into another one and delete it.
     */
    public function merge(Request $request, Product $product, ProductMerger $merger): ProductResource
    {
        $data = $request->validate([
            'into' => ['required', 'integer', 'exists:products,id', Rule::notIn([$product->id])],
        ]);

        $target = Product::findOrFail($data['into']);
        $merger->merge($product, $target);

        return new ProductResource($target->load(self::DETAIL_RELATIONS));
    }

    /**
     * Delete a product no recipe uses.
     */
    public function destroy(Product $product): Response
    {
        abort_if($product->ingredients()->exists(), 409, __('messages.product_in_use'));

        $product->delete();

        return response()->noContent();
    }
}
