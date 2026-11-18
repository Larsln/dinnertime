<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop the household buys at. A store that is not active, for example
 * while it is rebuilt, is left out when choosing where to buy.
 *
 * @property bool $is_active
 */
#[Fillable(['name', 'is_active'])]
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
