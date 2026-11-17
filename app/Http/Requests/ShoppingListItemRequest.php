<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'label' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'checked' => ['sometimes', 'boolean'],
        ];
    }
}
