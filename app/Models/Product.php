<?php

namespace App\Models;

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
use App\Models\Concerns\BroadcastsChanges;
use App\Services\Planning\PackageSize;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Purchasable product that ingredient lines refer to.
 *
 * A product stays suggested until a household member has confirmed its
 * details. Its conversions are reviewed individually, so a
 * confirmed product can receive new suggestions later.
 *
 * @property BaseUnit $base_unit
 * @property ReviewStatus $status
 * @property ?CarbonImmutable $enriched_at
 */
#[Fillable([
    'name',
    'plural_name',
    'icon',
    'store_section_id',
    'base_unit',
    'is_pantry_staple',
    'shelf_life_opened_days',
    'note',
    'status',
    'enriched_at',
    'mealie_food_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BroadcastsChanges, HasFactory;

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
            'base_unit' => BaseUnit::class,
            'is_pantry_staple' => 'boolean',
            'status' => ReviewStatus::class,
            'enriched_at' => 'immutable_datetime',
        ];
    }

    /**
     * Products with details or conversions awaiting confirmation.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function awaitingReview(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('status', ReviewStatus::Suggested)
            ->orWhereRelation('conversions', 'status', ReviewStatus::Suggested));
    }

    /**
     * Ingredient lines that use the product.
     *
     * @return HasMany<RecipeIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /**
     * Supermarket section the product is found in.
     *
     * @return BelongsTo<StoreSection, $this>
     */
    public function storeSection(): BelongsTo
    {
        return $this->belongsTo(StoreSection::class);
    }

    /**
     * Alternative spellings and synonyms.
     *
     * @return HasMany<ProductAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    /**
     * Conversions from product specific units into the base unit.
     *
     * @return HasMany<ProductConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(ProductConversion::class);
    }

    /**
     * Stores that sell the product, with package size and price.
     *
     * @return HasMany<ProductOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class)->orderBy('id');
    }

    /**
     * Sizes the shopping list may buy: those sold everywhere and those of
     * the stores the household prefers. Only when none of these exists do
     * the sizes of the other active stores count, so a store for special
     * things is used for what only it sells.
     *
     * @return Collection<int, PackageSize>
     */
    public function buyableSizes(): Collection
    {
        return once(function () {
            $offers = $this->offers
                ->each(fn (ProductOffer $offer) => $offer->setRelation('product', $this))
                ->filter(fn (ProductOffer $offer) => $offer->amountInBase() !== null && ($offer->store->is_active ?? true));
            $preferred = $offers->filter(fn (ProductOffer $offer) => $offer->store->is_preferred ?? true);

            return ($preferred->isEmpty() ? $offers : $preferred)
                ->sortBy(fn (ProductOffer $offer) => $offer->store === null ? 1 : 0)
                ->unique(fn (ProductOffer $offer) => round((float) $offer->amountInBase(), 3))
                ->map(fn (ProductOffer $offer) => new PackageSize($offer->id, $offer->store->name ?? '', (float) $offer->amountInBase()))
                ->values()
                ->toBase();
        });
    }
}
