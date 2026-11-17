<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreSectionResource;
use App\Models\StoreSection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoreSectionController extends Controller
{
    /**
     * List the store sections in walking order.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return StoreSectionResource::collection(StoreSection::orderBy('position')->get());
    }
}
