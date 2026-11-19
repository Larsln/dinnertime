<?php

namespace App\Models;

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
use App\Models\Concerns\BroadcastsChanges;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchasable product that ingredient lines refer to.
 *
 * A product stays suggested until a household member has confirmed its
 * details. Its conversions and packages are reviewed individually, so a
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
    private const float SIZE_TOLERANCE = 0.001;

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
     * Products with details, conversions or packages awaiting confirmation.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function awaitingReview(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('status', ReviewStatus::Suggested)
            ->orWhereRelation('conversions', 'status', ReviewStatus::Suggested)
            ->orWhereRelation('packages', 'status', ReviewStatus::Suggested));
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
     * Sizes the shopping list may buy: the standard sizes of the product
     * and the sizes stores the household currently buys at sell it in. A
     * size seen in a store carries the name of the store and the negated
     * id of its offer, which tells it apart from the standard sizes.
     *
     * @return Collection<int, ProductPackage>
     */
    public function buyableSizes(): Collection
    {
        return once(function () {
            $sizes = $this->packages->values();

            foreach ($this->offers as $offer) {
                $amount = $offer->setRelation('product', $this)->amountInBase();
                $isKnown = $sizes->contains(fn (ProductPackage $size) => abs($size->amount_in_base - $amount) < self::SIZE_TOLERANCE);

                if ($amount !== null && $offer->store->is_active && ! $isKnown) {
                    $sizes->push((new ProductPackage)->forceFill([
                        'id' => -$offer->id,
                        'label' => $offer->store->name,
                        'amount_in_base' => $amount,
                    ]));
                }
            }

            return $sizes;
        });
    }

    /**
     * Standard package sizes the product is sold in everywhere.
     *
     * @return HasMany<ProductPackage, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(ProductPackage::class);
    }
}
