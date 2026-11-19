<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MealType;
use App\Http\Controllers\Controller;
use App\Http\Resources\DinnerRoundResource;
use App\Http\Resources\MealPlanEntryResource;
use App\Models\DinnerRound;
use App\Models\MealPlanEntry;
use App\Services\Dinner\DinnerRoundStarter;
use App\Services\HouseholdSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DinnerRoundController extends Controller
{
    private const array RELATIONS = ['cards.recipe.tags', 'votes.user', 'match.tags'];

    /**
     * Show today's round, if one is running or matched.
     */
    public function current(): JsonResponse
    {
        $round = DinnerRound::openOn(CarbonImmutable::today())->latest('id')->first();

        return response()->json(['data' => $round === null ? null : new DinnerRoundResource($round->load(self::RELATIONS))]);
    }

    /**
     * Start today's round, or join the one that is running already.
     */
    public function store(Request $request, DinnerRoundStarter $starter): JsonResponse
    {
        $data = $request->validate([
            'is_solo' => ['sometimes', 'boolean'],
            'only_available' => ['sometimes', 'boolean'],
            'max_minutes' => ['sometimes', 'nullable', 'integer', 'between:5,600'],
        ]);

        $today = CarbonImmutable::today();
        $running = DinnerRound::openOn($today)->latest('id')->first();

        $round = $running ?? $starter->start(
            $request->user(),
            $today,
            $data['is_solo'] ?? false,
            $data['only_available'] ?? false,
            $data['max_minutes'] ?? null,
        );

        return (new DinnerRoundResource($round->load(self::RELATIONS)))
            ->response()
            ->setStatusCode($running === null ? 201 : 200);
    }

    /**
     * Say yes or no to a recipe. The first recipe enough members like
     * becomes the match and ends the voting.
     */
    public function vote(Request $request, DinnerRound $dinnerRound): DinnerRoundResource
    {
        $data = $request->validate([
            'recipe_id' => ['required', 'integer', Rule::exists('dinner_round_cards', 'recipe_id')->where('dinner_round_id', $dinnerRound->id)],
            'liked' => ['required', 'boolean'],
        ]);

        abort_if($dinnerRound->closed_at !== null || $dinnerRound->matched_recipe_id !== null, 409, __('messages.dinner_round_decided'));

        DB::transaction(function () use ($request, $dinnerRound, $data) {
            DinnerRound::whereKey($dinnerRound->id)->lockForUpdate()->first();

            $dinnerRound->votes()->updateOrCreate(
                ['recipe_id' => $data['recipe_id'], 'user_id' => $request->user()->id],
                ['liked' => $data['liked']],
            );

            $likes = $dinnerRound->votes()->where('recipe_id', $data['recipe_id'])->where('liked', true)->count();

            $dinnerRound->fill(['matched_recipe_id' => $likes >= $dinnerRound->likesToMatch() ? $data['recipe_id'] : null]);
            $dinnerRound->touch();
        });

        return new DinnerRoundResource($dinnerRound->refresh()->load(self::RELATIONS));
    }

    /**
     * Put the match on today's plan as dinner and close the round.
     */
    public function plan(DinnerRound $dinnerRound, HouseholdSettings $settings): MealPlanEntryResource
    {
        abort_if($dinnerRound->matched_recipe_id === null, 409, __('messages.dinner_round_without_match'));

        $entry = DB::transaction(function () use ($dinnerRound, $settings) {
            $dinnerRound->update(['closed_at' => now()]);

            return MealPlanEntry::create([
                'date' => $dinnerRound->date,
                'meal' => MealType::Dinner,
                'recipe_id' => $dinnerRound->matched_recipe_id,
                'servings' => $settings->defaultServings(),
            ]);
        });

        return new MealPlanEntryResource($entry->load('recipe.tags'));
    }

    /**
     * End a round without planning anything.
     */
    public function destroy(DinnerRound $dinnerRound): Response
    {
        $dinnerRound->update(['closed_at' => now()]);

        return response()->noContent();
    }
}
