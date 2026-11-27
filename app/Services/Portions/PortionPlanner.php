<?php

namespace App\Services\Portions;

use App\Enums\PortionVerdict;
use App\Models\MealPlanEntry;
use App\Models\PortionFeedback;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Decides how many portions a planned meal cooks from who eats it.
 *
 * Household members count with the portion that suits them, guests with
 * a standard portion. A meal that names nobody cooks one standard portion
 * per serving.
 */
class PortionPlanner
{
    /**
     * Verdicts per user and recipe, oldest first, loaded when first asked.
     *
     * @var array<int, array<int, list<array{0: PortionVerdict, 1: float}>>>|null
     */
    private ?array $histories = null;

    public function __construct(private readonly PortionEstimator $estimator) {}

    /**
     * Share of a standard portion of a recipe that suits a member.
     */
    public function factor(int $userId, int $recipeId): float
    {
        $recipes = $this->histories()[$userId] ?? [];

        return isset($recipes[$recipeId])
            ? $this->estimator->forRecipe($recipes[$recipeId])
            : $this->estimator->inGeneral(array_values(array_map($this->estimator->forRecipe(...), $recipes)));
    }

    /**
     * Set who eats a meal and derive its servings and portions.
     *
     * Without named members the servings stand as they are: when they
     * seat the whole household, every member eats and guests fill the
     * rest, otherwise nobody is named.
     *
     * @param  list<int>|null  $eaterIds  Members who eat, or null when only the servings are known.
     */
    public function compose(MealPlanEntry $entry, ?array $eaterIds, int $guests = 0): void
    {
        if ($eaterIds === null) {
            $members = User::orderBy('id')->pluck('id')->all();
            $seatsAll = $members !== [] && count($members) <= $entry->servings;
            $eaterIds = $seatsAll ? $members : null;
            $guests = $seatsAll ? $entry->servings - count($members) : 0;
        }

        $entry->eaters = $eaterIds === null ? null : array_map(fn (int $id) => ['user_id' => $id, 'factor' => self::standard()], $eaterIds);
        $entry->guests = $guests;
        $entry->servings = $eaterIds === null ? $entry->servings : count($eaterIds) + $guests;
        $this->refresh($entry);
    }

    /**
     * Bring the portions of a meal up to what suits its eaters now.
     */
    public function refresh(MealPlanEntry $entry): void
    {
        if ($entry->eaters === null) {
            $entry->portions = $entry->servings;

            return;
        }

        $entry->eaters = array_map(fn (array $eater) => [
            'user_id' => $eater['user_id'],
            'factor' => $this->factor($eater['user_id'], $entry->recipe_id),
        ], $entry->eaters);
        $entry->portions = round(array_sum(array_column($entry->eaters, 'factor')) + $entry->guests, 2);
    }

    /**
     * Let the meals still ahead follow a new verdict. Meals that were
     * cooked or already have a verdict keep the portions they were
     * served with.
     *
     * @return list<CarbonImmutable> Days whose meals changed.
     */
    public function followFeedback(): array
    {
        $this->histories = null;
        $days = [];

        $ahead = MealPlanEntry::query()
            ->whereDate('date', '>=', CarbonImmutable::today())
            ->whereNull('cooked_at')
            ->whereNotNull('eaters')
            ->doesntHave('feedback')
            ->get();

        foreach ($ahead as $entry) {
            $this->refresh($entry);

            if ($entry->isDirty()) {
                $entry->save();
                $days[] = $entry->date;
            }
        }

        return $days;
    }

    private static function standard(): float
    {
        return PortionEstimator::STANDARD;
    }

    /**
     * @return array<int, array<int, list<array{0: PortionVerdict, 1: float}>>>
     */
    private function histories(): array
    {
        if ($this->histories === null) {
            $this->histories = [];

            foreach (PortionFeedback::orderBy('created_at')->orderBy('id')->get() as $feedback) {
                $this->histories[$feedback->user_id][$feedback->recipe_id][] = [$feedback->verdict, $feedback->served_factor];
            }
        }

        return $this->histories;
    }
}
