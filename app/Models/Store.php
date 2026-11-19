<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop the household buys at. A store that is not active, for example
 * while it is rebuilt, is left out when choosing where to buy. Preferred
 * stores are where the household usually goes: their package sizes are
 * bought first.
 *
 * @property bool $is_active
 * @property bool $is_preferred
 */
#[Fillable(['name', 'is_active', 'is_preferred'])]
class Store extends Model
{
    use BroadcastsChanges;

    public static function broadcastArea(): string
    {
        return 'products';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_preferred' => 'boolean',
        ];
    }

    /**
     * The store known under the name, ignoring case, or a new one.
     */
    public static function named(string $name): self
    {
        return self::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])->first()
            ?? self::create(['name' => trim($name)])->refresh();
    }

    /**
     * Products noted as sold here.
     *
     * @return HasMany<ProductOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }
}
