<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    /**
     * List the fixed meal types, categories and tags.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::orderBy('type')->orderBy('position')->get());
    }
}
