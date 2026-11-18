<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateMealPlanRequest;
use App\Http\Requests\MealPlanEntryRequest;
use App\Http\Requests\PeriodRequest;
use App\Http\Resources\MealPlanEntryResource;
use App\Models\MealPlanEntry;
use App\Services\Planning\MealPlanGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MealPlanController extends Controller
{
    /**
     * List the entries of a period.
     */
    public function index(PeriodRequest $request): AnonymousResourceCollection
    {
        $entries = MealPlanEntry::query()
            ->with('recipe.tags')
            ->whereBetween('date', [$request->from(), $request->to()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return MealPlanEntryResource::collection($entries);
    }

    /**
     * Schedule a recipe.
     */
    public function store(MealPlanEntryRequest $request): MealPlanEntryResource
    {
        $entry = MealPlanEntry::create($request->safe()->except('cooked'));

        return new MealPlanEntryResource($entry->load('recipe.tags'));
    }

    /**
     * Change an entry or mark it as cooked.
     */
    public function update(MealPlanEntryRequest $request, MealPlanEntry $entry): MealPlanEntryResource
    {
        $entry->fill($request->safe()->except('cooked'));

        if ($request->has('cooked')) {
            $entry->cooked_at = $request->boolean('cooked') ? ($entry->cooked_at ?? now()) : null;
        }

        $entry->save();

        return new MealPlanEntryResource($entry->load('recipe.tags'));
    }

    /**
     * Remove an entry.
     */
    public function destroy(MealPlanEntry $entry): Response
    {
        $entry->delete();

        return response()->noContent();
    }

    /**
     * Fill the chosen days and meals of a period with recipes that use up
     * leftovers. Meals that already have an entry are left as they are.
     */
    public function generate(GenerateMealPlanRequest $request, MealPlanGenerator $generator): JsonResponse
    {
        $entries = $generator->generate(
            $request->from(),
            $request->to(),
            $request->slots(),
            $request->integer('servings'),
        );

        return MealPlanEntryResource::collection($entries->each->load('recipe.tags'))
            ->response()
            ->setStatusCode(201);
    }
}
