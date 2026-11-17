<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Package size a product is typically sold in, measured in its base unit.
 *
 * @property float $amount_in_base
 * @property ReviewStatus $status
 * @property ValueSource $source
 */
#[Fillable(['product_id', 'label', 'amount_in_base', 'status', 'source'])]
class ProductPackage extends Model
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
     * Product sold in this package.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
