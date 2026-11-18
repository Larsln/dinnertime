<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductOfferRequest;
use App\Http\Resources\ProductOfferResource;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductOfferController extends Controller
{
    /**
     * List the stores named so far, the most used first.
     */
    public function stores(): JsonResponse
    {
        return response()->json(['data' => ProductOffer::query()
            ->groupBy('store')
            ->orderByRaw('count(*) desc')
            ->orderBy('store')
            ->pluck('store')]);
    }

    /**
     * Note where a product can be bought.
     */
    public function store(ProductOfferRequest $request, Product $product): ProductOfferResource
    {
        return new ProductOfferResource($product->offers()->create($request->validated()));
    }

    /**
     * Correct an offer.
     */
    public function update(ProductOfferRequest $request, ProductOffer $offer): ProductOfferResource
    {
        $offer->update($request->validated());

        return new ProductOfferResource($offer);
    }

    /**
     * Remove an offer.
     */
    public function destroy(ProductOffer $offer): Response
    {
        $offer->delete();

        return response()->noContent();
    }
}
