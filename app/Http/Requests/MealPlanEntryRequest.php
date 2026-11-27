<?php

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a plan entry. All fields are required when creating an entry
 * and optional when changing one. Who eats is given either as a number of
 * servings or as the members who eat plus a number of guests.
 */
class MealPlanEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'date' => [$presence, 'date_format:Y-m-d'],
            'meal' => [$presence, Rule::enum(MealType::class)],
            'recipe_id' => [$presence, 'integer', 'exists:recipes,id'],
            'servings' => ['sometimes', 'integer', 'between:1,50'],
            'cooked' => ['sometimes', 'boolean'],
            'eater_ids' => ['sometimes', 'array', 'max:50'],
            'eater_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'guests' => ['sometimes', 'integer', 'between:0,50', Rule::when($this->input('eater_ids') === [], 'min:1')],
        ];
    }

    /**
     * Members who eat, when the request names them.
     *
     * @return list<int>|null
     */
    public function eaterIds(): ?array
    {
        return $this->has('eater_ids') ? array_map(intval(...), $this->validated('eater_ids')) : null;
    }
}
