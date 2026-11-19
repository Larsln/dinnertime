<?php

namespace App\Services\Dinner;

use App\Enums\MealType;
use App\Models\DinnerRound;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
use App\Models\User;
use App\Services\HouseholdSettings;
use App\Services\Planning\ShoppingForecaster;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * Starts a dinner round with recipes worth deciding on tonight: dinners
 * the household did not have lately, those that need the least shopping
 * first.
 */
class DinnerRoundStarter
{
    public const int CARDS = 12;

    private const int RECENT_DAYS = 7;

    public function __construct(
        private readonly RecipeAvailability $availability,
        private readonly HouseholdSettings $settings,
        private readonly Randomizer $randomizer,
    ) {}

    /**
     * @param  bool  $onlyAvailable  Leave out recipes that need shopping.
     * @param  ?int  $maxMinutes  Longest preparation and cooking time, if limited.
     */
    public function start(User $user, CarbonImmutable $date, bool $isSolo, bool $onlyAvailable, ?int $maxMinutes): DinnerRound
    {
        $pantry = PantryItem::all();
        $servings = $this->settings->defaultServings();
        $cards = [];

        foreach ($this->randomizer->shuffleArray($this->candidates($date, $maxMinutes)->all()) as $recipe) {
            $missing = $this->availability->missingCount($recipe, $servings, $pantry);

            if (! $onlyAvailable || $missing === 0) {
                $cards[] = ['recipe_id' => $recipe->id, 'missing_count' => $missing];
            }
        }

        usort($cards, fn (array $a, array $b) => $a['missing_count'] <=> $b['missing_count']);

        return DB::transaction(function () use ($user, $date, $isSolo, $cards) {
            $round = DinnerRound::create(['date' => $date, 'is_solo' => $isSolo, 'started_by' => $user->id]);

            foreach (array_slice($cards, 0, self::CARDS) as $position => $card) {
                $round->cards()->create([...$card, 'position' => $position]);
            }

            return $round;
        });
    }

    /**
     * Dinner recipes that were not planned in the past days.
     *
     * @return Collection<int, Recipe>
     */
    private function candidates(CarbonImmutable $date, ?int $maxMinutes)
    {
        $recent = MealPlanEntry::query()
            ->whereBetween('date', [$date->subDays(self::RECENT_DAYS), $date])
            ->pluck('recipe_id');

        return Recipe::query()
            ->with(ShoppingForecaster::RECIPE_RELATIONS)
            ->whereHas('tags', fn (Builder $query) => $query->where('key', MealType::Dinner->value))
            ->whereNotIn('id', $recent)
            ->when($maxMinutes, fn (Builder $query, int $minutes) => $query
                ->whereRaw('coalesce(prep_minutes, 0) + coalesce(cook_minutes, 0) between 1 and ?', [$minutes]))
            ->get();
    }
}
