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
use App\Services\HouseholdSettings;
use App\Services\Planning\MealPlanGenerator;
use App\Services\Planning\ShoppingListRefresher;
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
     * Schedule a recipe. Shopping lists covering the day follow.
     */
    public function store(MealPlanEntryRequest $request, HouseholdSettings $settings, ShoppingListRefresher $lists): MealPlanEntryResource
    {
        $entry = MealPlanEntry::create([
            'servings' => $settings->defaultServings(),
            ...$request->safe()->except('cooked'),
        ]);

        $lists->afterChangesOn([$entry->date]);

        return new MealPlanEntryResource($entry->load('recipe.tags'));
    }

    /**
     * Change, move or swap an entry or mark it as cooked. Shopping lists
     * covering the old or the new day follow.
     */
    public function update(MealPlanEntryRequest $request, MealPlanEntry $entry, ShoppingListRefresher $lists): MealPlanEntryResource
    {
        $previousDate = $entry->date;
        $entry->fill($request->safe()->except('cooked'));

        if ($request->has('cooked')) {
            $entry->cooked_at = $request->boolean('cooked') ? ($entry->cooked_at ?? now()) : null;
        }

        $changesShopping = $entry->isDirty(['date', 'recipe_id', 'servings']);
        $entry->save();

        if ($changesShopping) {
            $lists->afterChangesOn([$previousDate, $entry->date]);
        }

        return new MealPlanEntryResource($entry->load('recipe.tags'));
    }

    /**
     * Remove an entry. Shopping lists covering the day follow.
     */
    public function destroy(MealPlanEntry $entry, ShoppingListRefresher $lists): Response
    {
        $entry->delete();
        $lists->afterChangesOn([$entry->date]);

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
     * Schedule several recipes at once. Shopping lists covering the days
     * follow.
     */
    public function storeBatch(Request $request, ShoppingListRefresher $lists): JsonResponse
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

        $lists->afterChangesOn(array_map(fn (MealPlanEntry $entry) => $entry->date, $entries));

        return MealPlanEntryResource::collection($entries)->response()->setStatusCode(201);
    }
}
