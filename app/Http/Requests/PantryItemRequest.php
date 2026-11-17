<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a pantry item. Product and amount are required when creating
 * an item, everything is optional when changing one.
 */
class PantryItemRequest extends FormRequest
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
            'product_id' => [$presence, 'integer', 'exists:products,id'],
            'amount' => [$presence, 'numeric', 'gt:0'],
            'opened_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'expires_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
