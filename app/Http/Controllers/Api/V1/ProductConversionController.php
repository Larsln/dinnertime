<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductConversionResource;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductConversionController extends Controller
{
    /**
     * Set how much of the product one unit corresponds to. Values entered
     * by a user count as confirmed.
     */
    public function update(Request $request, Product $product, Unit $unit): ProductConversionResource
    {
        $data = $request->validate([
            'amount_in_base' => ['required', 'numeric', 'gt:0'],
        ]);

        $conversion = $product->conversions()->updateOrCreate(['unit_id' => $unit->id], [
            'amount_in_base' => $data['amount_in_base'],
            'status' => ReviewStatus::Confirmed,
            'source' => ValueSource::User,
        ]);

        return new ProductConversionResource($conversion->load('unit'));
    }

    /**
     * Remove the conversion for a unit.
     */
    public function destroy(Product $product, Unit $unit): Response
    {
        $product->conversions()->where('unit_id', $unit->id)->delete();

        return response()->noContent();
    }
}
