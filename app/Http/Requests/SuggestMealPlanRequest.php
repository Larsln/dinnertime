<?php

namespace App\Http\Requests;

use App\Enums\Course;
use App\Enums\MealType;
use App\Services\HouseholdSettings;
use App\Services\Planning\PlanPreferences;
use App\Services\Planning\PlanRequest;
use App\Services\Planning\PlanSlot;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Validates the slots, preferences and filters of a plan suggestion.
 */
class SuggestMealPlanRequest extends PeriodRequest
{
    private const array PREFERENCES = ['use_leftovers', 'quick_on_weekdays', 'prefer_not_recent', 'prefer_favorites', 'variety'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:to'],
            'slots.*.meal' => ['required', Rule::enum(MealType::class)],
            'servings' => ['sometimes', 'integer', 'between:1,50'],
            'preferences' => ['sometimes', 'array:'.implode(',', self::PREFERENCES)],
            'preferences.*' => ['boolean'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'courses' => ['sometimes', 'array'],
            'courses.*' => [Rule::enum(Course::class)],
            ...$this->pinRules('keep'),
            ...$this->pinRules('avoid'),
        ];
    }

    /**
     * The validated input as request for the generator.
     */
    public function toPlanRequest(): PlanRequest
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return new PlanRequest(
            from: $this->from(),
            to: $this->to(),
            slots: $this->slots($data['slots']),
            servings: (int) ($data['servings'] ?? app(HouseholdSettings::class)->defaultServings()),
            preferences: PlanPreferences::fromArray(array_map(boolval(...), $data['preferences'] ?? [])),
            user: $this->user(),
            tagIds: $data['tag_ids'] ?? [],
            courses: array_map(Course::from(...), $data['courses'] ?? []),
            keep: $this->pins($data['keep'] ?? []),
            avoid: $this->pins($data['avoid'] ?? []),
        );
    }

    /**
     * Rules for a list that ties recipes to slots.
     *
     * @return array<string, list<mixed>>
     */
    private function pinRules(string $field): array
    {
        return [
            $field => ['sometimes', 'array'],
            "{$field}.*.date" => ['required', 'date_format:Y-m-d'],
            "{$field}.*.meal" => ['required', Rule::enum(MealType::class)],
            "{$field}.*.recipe_id" => ['required', 'integer', 'exists:recipes,id'],
        ];
    }

    /**
     * The slots to fill, each once and in chronological order.
     *
     * @param  list<array{date: string, meal: string}>  $slots
     * @return list<PlanSlot>
     */
    private function slots(array $slots): array
    {
        return collect($slots)
            ->map(fn (array $slot) => new PlanSlot(CarbonImmutable::parse($slot['date']), MealType::from($slot['meal'])))
            ->unique(fn (PlanSlot $slot) => $slot->key())
            ->sortBy(fn (PlanSlot $slot) => $slot->date)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{date: string, meal: string, recipe_id: int}>  $pins
     * @return array<string, int> Recipe ids keyed by slot key.
     */
    private function pins(array $pins): array
    {
        return collect($pins)
            ->mapWithKeys(fn (array $pin) => ["{$pin['date']}|{$pin['meal']}" => (int) $pin['recipe_id']])
            ->all();
    }
}
