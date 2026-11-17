<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductPackageRequest;
use App\Http\Resources\ProductPackageResource;
use App\Models\Product;
use App\Models\ProductPackage;
use Illuminate\Http\Response;

class ProductPackageController extends Controller
{
    private const array USER_VALUE = ['status' => ReviewStatus::Confirmed, 'source' => ValueSource::User];

    /**
     * Add a package size. Values entered by a user count as confirmed.
     */
    public function store(ProductPackageRequest $request, Product $product): ProductPackageResource
    {
        return new ProductPackageResource(
            $product->packages()->create([...$request->validated(), ...self::USER_VALUE]),
        );
    }

    /**
     * Correct a package size, which confirms it.
     */
    public function update(ProductPackageRequest $request, ProductPackage $package): ProductPackageResource
    {
        $package->update([...$request->validated(), ...self::USER_VALUE]);

        return new ProductPackageResource($package);
    }

    /**
     * Remove a package size.
     */
    public function destroy(ProductPackage $package): Response
    {
        $package->delete();

        return response()->noContent();
    }
}
