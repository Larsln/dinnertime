<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CourseController extends Controller
{
    /**
     * List the courses a recipe can belong to.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (Course $course) => ['key' => $course->value, 'name' => $course->label()],
                Course::cases(),
            ),
        ]);
    }
}
