<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a manual shopping list position or a change to any position.
 */
class ShoppingListItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => [$this->isMethod('POST') ? 'required_without:product_id' : 'sometimes', 'string', 'max:255'],
            'product_id' => ['sometimes', 'integer', Rule::exists('products', 'id')],
            'checked' => ['sometimes', 'boolean'],
            'skipped' => ['sometimes', 'boolean'],
            'substitute_product_id' => ['sometimes', 'nullable', 'integer', Rule::exists('products', 'id')],
            'substitute_label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
