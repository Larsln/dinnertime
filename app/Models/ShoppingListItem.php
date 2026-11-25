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
 * Products bought as the position's product are listed as its variants.
 *
 * A position is settled by checking it off or by skipping it. A skipped
 * one is not bought at all, and one checked off with a substitute was
 * replaced by something else, so neither adds its product to the stock.
 *
 * @property ?list<array{package_id: int, label: string, amount: float, count: int}> $packages
 * @property ?list<array{date: string, recipe_id: int, title: string, amount: float}> $uses
 * @property ?list<array{unit_id: ?int, quantity: float}> $extra_amounts
 * @property ?list<array<string, mixed>> $variants Amounts of the products bought as this one, shaped like the position's own.
 * @property ?CarbonImmutable $checked_at
 * @property ?CarbonImmutable $skipped_at
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
    'variants',
    'checked_at',
    'checked_by',
    'skipped_at',
    'substitute_product_id',
    'substitute_label',
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
            'variants' => 'array',
            'checked_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
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

    /**
     * Product that was bought in place of the position's one.
     *
     * @return BelongsTo<Product, $this>
     */
    public function substituteProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'substitute_product_id');
    }

    /**
     * Whether nothing is left to do about the position.
     */
    public function isSettled(): bool
    {
        return $this->checked_at !== null || $this->skipped_at !== null;
    }

    /**
     * Whether something else was bought in its place.
     */
    public function isSubstituted(): bool
    {
        return $this->substitute_product_id !== null || $this->substitute_label !== null;
    }

    /**
     * Whether the position's product itself was bought.
     */
    public function isBought(): bool
    {
        return $this->checked_at !== null && $this->skipped_at === null && ! $this->isSubstituted();
    }

    /**
     * Ids of the position's product and of its variants.
     *
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_values(array_filter([$this->product_id, ...array_column($this->variants ?? [], 'product_id')]));
    }
}
