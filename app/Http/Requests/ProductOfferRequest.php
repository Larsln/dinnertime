<?php

namespace App\Http\Requests;

use App\Enums\OfferUnit;
use App\Models\Product;
use App\Models\ProductOffer;
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
            'amount' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'unit' => ['sometimes', 'nullable', Rule::enum(OfferUnit::class)],
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

        return $this->withUnit($data);
    }

    /**
     * A size always has a unit and no size has none. Without a stated
     * unit the size counts in the unit the offer had, or in the base unit
     * of the product.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withUnit(array $data): array
    {
        $offer = $this->route('offer');
        $product = $offer instanceof ProductOffer ? $offer->product : $this->route('product');
        $amount = array_key_exists('amount', $data) ? $data['amount'] : $offer?->amount;

        if ($amount === null) {
            return array_key_exists('amount', $data) || array_key_exists('unit', $data) ? [...$data, 'unit' => null] : $data;
        }

        $fallback = ($offer instanceof ProductOffer ? $offer->unit : null) ?? ($product instanceof Product ? OfferUnit::from($product->base_unit->value) : OfferUnit::Gram);

        return [...$data, 'unit' => $data['unit'] ?? $fallback];
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
