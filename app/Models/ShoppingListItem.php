<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position on a shopping list.
 *
 * Generated positions refer to a product and state the packages to buy,
 * manual positions and ingredients without a product only carry a label.
 *
 * @property ?list<array{package_id: int, label: string, amount: float, count: int}> $packages
 * @property ?list<array{date: string, recipe_id: int, title: string, amount: float}> $uses
 * @property ?list<array{unit_id: ?int, quantity: float}> $extra_amounts
 * @property ?CarbonImmutable $checked_at
 */
#[Fillable([
    'shopping_list_id',
    'product_id',
    'label',
    'is_manual',
    'needed_amount',
    'purchase_amount',
    'packages',
    'leftover_amount',
    'wasted_amount',
    'uses',
    'is_approximate',
    'extra_amounts',
    'checked_at',
    'checked_by',
])]
class ShoppingListItem extends Model
{
    use BroadcastsChanges;

    public static function broadcastArea(): string
    {
        return 'shopping';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_manual' => 'boolean',
            'needed_amount' => 'float',
            'purchase_amount' => 'float',
            'packages' => 'array',
            'leftover_amount' => 'float',
            'wasted_amount' => 'float',
            'uses' => 'array',
            'is_approximate' => 'boolean',
            'extra_amounts' => 'array',
            'checked_at' => 'immutable_datetime',
        ];
    }

    /**
     * List the position belongs to.
     *
     * @return BelongsTo<ShoppingList, $this>
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * Product to buy, empty for free-text positions.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
