<?php

namespace App\Http\Requests;

use App\Enums\BaseUnit;
use App\Support\ProductIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a product. The name is required when creating a product,
 * everything is optional when changing one.
 */
class ProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('products', 'name')->ignore($this->route('product')),
            ],
            'plural_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', Rule::in(ProductIcons::keys())],
            'store_section_id' => ['sometimes', 'nullable', 'integer', 'exists:store_sections,id'],
            'base_unit' => ['sometimes', Rule::enum(BaseUnit::class)],
            'is_pantry_staple' => ['sometimes', 'boolean'],
            'shelf_life_opened_days' => ['sometimes', 'nullable', 'integer', 'between:1,3650'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
