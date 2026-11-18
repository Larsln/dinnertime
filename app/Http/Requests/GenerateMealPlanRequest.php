<?php

namespace App\Http\Requests;

use App\Enums\MealType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

class GenerateMealPlanRequest extends PeriodRequest
{
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
            'servings' => ['required', 'integer', 'between:1,50'],
        ];
    }

    /**
     * The days and meals to fill, each once and in chronological order.
     *
     * @return list<array{date: CarbonImmutable, meal: MealType}>
     */
    public function slots(): array
    {
        /** @var list<array{date: string, meal: string}> $slots */
        $slots = $this->validated('slots');

        return collect($slots)
            ->unique(fn (array $slot) => "{$slot['date']}|{$slot['meal']}")
            ->sortBy('date')
            ->map(fn (array $slot) => [
                'date' => CarbonImmutable::parse($slot['date']),
                'meal' => MealType::from($slot['meal']),
            ])
            ->values()
            ->all();
    }
}
