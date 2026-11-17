<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Amount of a product that is in stock, measured in its base unit.
 *
 * @property float $amount
 * @property ?CarbonImmutable $opened_on
 * @property ?CarbonImmutable $expires_on
 */
#[Fillable(['product_id', 'amount', 'opened_on', 'expires_on'])]
class PantryItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'opened_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
        ];
    }

    /**
     * Product that is in stock.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
