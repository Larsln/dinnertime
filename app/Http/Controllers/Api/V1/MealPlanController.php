<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MealType;
use App\Http\Controllers\Controller;
use App\Http\Requests\MealPlanEntryRequest;
use App\Http\Requests\PeriodRequest;
use App\Http\Requests\SuggestMealPlanRequest;
use App\Http\Resources\MealPlanEntryResource;
use App\Http\Resources\SlotAssignmentResource;
use App\Models\MealPlanEntry;
use App\Services\Planning\MealPlanGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
     * Suggest recipes for chosen days and meals without saving them.
     * Clients show the suggestion, let the user lock or re-roll single
     * meals and save what is accepted with the batch endpoint.
     */
    public function suggest(SuggestMealPlanRequest $request, MealPlanGenerator $generator): AnonymousResourceCollection
    {
        $suggestion = $generator->suggest($request->toPlanRequest());

        return SlotAssignmentResource::collection($suggestion->assignments)
            ->additional(['meta' => ['candidate_count' => $suggestion->candidateCount]]);
    }

    /**
     * Schedule several recipes at once.
     */
    public function storeBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.date' => ['required', 'date_format:Y-m-d'],
            'entries.*.meal' => ['required', Rule::enum(MealType::class)],
            'entries.*.recipe_id' => ['required', 'integer', 'exists:recipes,id'],
            'entries.*.servings' => ['required', 'integer', 'between:1,50'],
        ]);

        $entries = DB::transaction(fn () => array_map(
            fn (array $entry) => MealPlanEntry::create($entry)->load('recipe.tags'),
            $data['entries'],
        ));

        return MealPlanEntryResource::collection($entries)->response()->setStatusCode(201);
    }
}
