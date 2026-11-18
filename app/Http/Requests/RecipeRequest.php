<?php

namespace App\Http\Requests;

use App\Enums\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a recipe. The title is required when creating a recipe,
 * everything is optional when changing one. Nested lists replace the
 * existing ones as a whole.
 */
class RecipeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'course' => ['sometimes', 'nullable', Rule::enum(Course::class)],
            'servings' => ['sometimes', 'nullable', 'integer', 'between:1,100'],
            'yield_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prep_minutes' => ['sometimes', 'nullable', 'integer', 'between:0,10000'],
            'cook_minutes' => ['sometimes', 'nullable', 'integer', 'between:0,10000'],
            'source_url' => ['sometimes', 'nullable', 'url', 'max:2048'],

            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],

            'tools' => ['sometimes', 'array'],
            'tools.*' => ['string', 'distinct', 'max:100'],

            'ingredients' => ['sometimes', 'array'],
            'ingredients.*.section' => ['nullable', 'string', 'max:255'],
            'ingredients.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'ingredients.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'ingredients.*.note' => ['nullable', 'string', 'max:255'],
            'ingredients.*.is_estimated' => ['sometimes', 'boolean'],
            'ingredients.*.raw_text' => ['nullable', 'string', 'max:255', 'required_without:ingredients.*.product_id'],

            'steps' => ['sometimes', 'array'],
            'steps.*.title' => ['nullable', 'string', 'max:255'],
            'steps.*.text' => ['required', 'string', 'max:5000'],
        ];
    }
}
