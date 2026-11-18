<?php

namespace App\Http\Requests;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'store_id' => [$this->isMethod('POST') ? 'required_without:store' : 'sometimes', 'integer', Rule::exists('stores', 'id')],
            'store' => ['sometimes', 'string', 'max:100'],
            'amount_in_base' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }

    /**
     * The offer as it is stored: a store given by name is looked up
     * ignoring case and created if it is new.
     *
     * @return array<string, mixed>
     */
    public function offer(): array
    {
        $data = $this->safe()->except('store');

        if (! $this->has('store_id') && $this->filled('store')) {
            $data['store_id'] = Store::named($this->validated('store'))->id;
        }

        return $data;
    }

    /**
     * Store names are compared without surrounding spaces.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('store'))) {
            $this->merge(['store' => trim($this->input('store'))]);
        }
    }
}
