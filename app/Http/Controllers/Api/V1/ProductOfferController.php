<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductOfferRequest;
use App\Http\Resources\ProductOfferResource;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Http\Response;

class ProductOfferController extends Controller
{
    /**
     * Note where a product can be bought.
     */
    public function store(ProductOfferRequest $request, Product $product): ProductOfferResource
    {
        $offer = $product->offers()->create($request->offer());

        return new ProductOfferResource($offer->load('store'));
    }

    /**
     * Correct an offer.
     */
    public function update(ProductOfferRequest $request, ProductOffer $offer): ProductOfferResource
    {
        $offer->update($request->offer());

        return new ProductOfferResource($offer->load('store'));
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
