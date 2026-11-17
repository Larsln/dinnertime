<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a package size, which must be unique per product.
 */
class ProductPackageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';
        $package = $this->route('package');
        $product = $this->route('product');

        return [
            'label' => [$presence, 'string', 'max:100'],
            'amount_in_base' => [
                $presence,
                'numeric',
                'gt:0',
                Rule::unique('product_packages', 'amount_in_base')
                    ->where('product_id', $package instanceof ProductPackage ? $package->product_id : ($product instanceof Product ? $product->id : null))
                    ->ignore($package),
            ],
        ];
    }
}
