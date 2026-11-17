<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shopping list covering the meals planned for a period.
 *
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property ?CarbonImmutable $leftovers_stocked_at
 */
#[Fillable(['starts_on', 'ends_on', 'leftovers_stocked_at'])]
class ShoppingList extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'leftovers_stocked_at' => 'immutable_datetime',
        ];
    }

    /**
     * Positions on the list.
     *
     * @return HasMany<ShoppingListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class);
    }
}
