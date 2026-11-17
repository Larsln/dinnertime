<?php

namespace App\Http\Requests;

use App\Enums\MealType;
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
            'meals' => ['required', 'array', 'min:1'],
            'meals.*' => ['distinct', Rule::enum(MealType::class)],
            'servings' => ['required', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return list<MealType>
     */
    public function meals(): array
    {
        return array_map(MealType::from(...), $this->validated('meals'));
    }
}
