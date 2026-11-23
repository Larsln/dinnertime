<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealPlanEntryResource;
use App\Models\MealPlanEntry;
use App\Services\Planning\ShoppingListRefresher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MealPlanShiftController extends Controller
{
    private const int MAX_DAYS = 14;

    /**
     * Move every meal not cooked yet from a day on by some days, for when
     * the household eats something else at short notice. Shopping lists
     * covering the days before and after follow.
     */
    public function __invoke(Request $request, ShoppingListRefresher $lists): AnonymousResourceCollection
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'days' => ['required', 'integer', 'between:-'.self::MAX_DAYS.','.self::MAX_DAYS, 'not_in:0'],
        ]);

        $entries = MealPlanEntry::query()
            ->whereNull('cooked_at')
            ->whereDate('date', '>=', $data['from'])
            ->orderBy('date')
            ->get();
        $days = $entries->map(fn (MealPlanEntry $entry) => $entry->date)->all();

        DB::transaction(fn () => $entries->each(fn (MealPlanEntry $entry) => $entry->update(['date' => $entry->date->addDays($data['days'])])));

        $lists->afterChangesOn([...$days, ...$entries->map(fn (MealPlanEntry $entry) => $entry->date)->all()]);

        return MealPlanEntryResource::collection($entries->load('recipe.tags'));
    }
}
