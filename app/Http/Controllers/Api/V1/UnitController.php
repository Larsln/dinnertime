<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    /**
     * List the units of measurement.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return UnitResource::collection(Unit::orderBy('id')->get());
    }
}
