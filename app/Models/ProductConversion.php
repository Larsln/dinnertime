<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Amount of a product, in its base unit, that one unit corresponds to.
 */
#[Fillable(['product_id', 'unit_id', 'amount_in_base', 'status', 'source'])]
class ProductConversion extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_in_base' => 'float',
            'status' => ReviewStatus::class,
            'source' => ValueSource::class,
        ];
    }

    /**
     * Product the conversion belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Unit that is converted.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
