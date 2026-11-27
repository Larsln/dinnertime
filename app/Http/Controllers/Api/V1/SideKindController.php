<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SideKind;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SideKindController extends Controller
{
    /**
     * List the kinds a side dish can be of.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (SideKind $kind) => ['key' => $kind->value, 'name' => $kind->label()],
                SideKind::cases(),
            ),
        ]);
    }
}
