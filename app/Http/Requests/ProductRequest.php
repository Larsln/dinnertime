<?php

namespace App\Http\Requests;

use App\Enums\BaseUnit;
use App\Models\Product;
use App\Support\ProductIcons;
use Closure;
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
            'buy_as_id' => ['sometimes', 'nullable', 'integer', 'exists:products,id', $this->buyAsRule()],
        ];
    }

    /**
     * A product is bought as another one only a single level deep: not as
     * itself, not as a product that is bought as something else, and not
     * while others are bought as it.
     */
    private function buyAsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $product = $this->route('product');
            $target = Product::find($value);

            if ($product instanceof Product && ($target?->is($product) || $product->variants()->exists())) {
                $fail(__('messages.buy_as_nested'));
            } elseif ($target?->buy_as_id !== null) {
                $fail(__('messages.buy_as_nested'));
            }
        };
    }
}
