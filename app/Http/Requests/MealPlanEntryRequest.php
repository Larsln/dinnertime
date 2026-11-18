<?php

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a plan entry. All fields are required when creating an entry
 * and optional when changing one.
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
        ];
    }
}
