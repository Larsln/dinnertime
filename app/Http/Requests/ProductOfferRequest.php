<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates where a product can be bought, in which size and at what price.
 */
class ProductOfferRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'store' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:100'],
            'amount_in_base' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }

    /**
     * Store names are compared as written, so stray spaces are removed.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('store'))) {
            $this->merge(['store' => trim($this->input('store'))]);
        }
    }
}
