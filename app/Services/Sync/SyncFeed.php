<?php

namespace App\Services\Sync;

use App\Http\Resources\MealPlanEntryResource;
use App\Http\Resources\PantryItemResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\ShoppingListResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\StoreSectionResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\ToolResource;
use App\Http\Resources\UnitResource;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\Store;
use App\Models\StoreSection;
use App\Models\Tag;
use App\Models\Tool;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tells a client what changed since the change number it last saw, so it
 * can keep a copy of the household's data without loading everything
 * again.
 */
class SyncFeed
{
    /**
     * Key of the lock every change takes before it gets its number.
     */
    private const int LOCK = 7316001;

    /**
     * The kinds of records a client keeps a copy of.
     */
    public const array KINDS = [
        'units', 'store_sections', 'tags', 'tools', 'stores', 'products', 'recipes',
        'meal_plan_entries', 'pantry_items', 'shopping_lists',
    ];

    /**
     * Everything that changed after `$since`, at most `$limit` records
     * of every kind. A client that is told there is more asks again with
     * the returned version.
     *
     * @param  list<string>|null  $kinds  Kinds to report; all of them when null.
     */
    public function changesSince(int $since, int $limit, User $user, ?array $kinds = null): SyncChanges
    {
        return DB::transaction(function () use ($since, $limit, $user, $kinds) {
            $entities = array_intersect_key($this->entities($user), array_flip($kinds ?? self::KINDS));
            $latest = $this->latestVersion();
            $version = $latest;
            $records = [];

            foreach ($entities as $name => [$query]) {
                $records[$name] = $query
                    ->where('sync_version', '>', $since)
                    ->where('sync_version', '<=', $latest)
                    ->orderBy('sync_version')
                    ->limit($limit + 1)
                    ->get();

                if ($records[$name]->count() > $limit) {
                    $version = min($version, $records[$name][$limit - 1]->getAttribute('sync_version'));
                }
            }

            $changed = [];

            foreach ($entities as $name => [, $resource]) {
                $changed[$name] = $resource::collection($records[$name]->where('sync_version', '<=', $version)->values());
            }

            return new SyncChanges($version, $version < $latest, $changed, $this->deletions($since, $version));
        });
    }

    /**
     * The number of the latest change. Waiting for the lock lets changes
     * that already hold a number finish first.
     */
    private function latestVersion(): int
    {
        DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK]);

        return (int) DB::scalar('SELECT CASE WHEN is_called THEN last_value ELSE 0 END FROM sync_versions');
    }

    /**
     * Ids of the records deleted in the given range, by kind. A client
     * that starts from nothing has nothing to delete.
     *
     * @return array<string, list<int>>
     */
    private function deletions(int $since, int $version): array
    {
        if ($since === 0) {
            return [];
        }

        return DB::table('sync_deletions')
            ->where('sync_version', '>', $since)
            ->where('sync_version', '<=', $version)
            ->get()
            ->groupBy('entity')
            ->map(fn (Collection $rows) => $rows->pluck('entity_id')->all())
            ->all();
    }

    /**
     * The kinds of records a client keeps, each with the query that loads
     * them the way the API shows them and the resource that renders them.
     *
     * @return array<string, array{Builder<covariant \Illuminate\Database\Eloquent\Model>, class-string<JsonResource>}>
     */
    private function entities(User $user): array
    {
        return [
            'units' => [Unit::query(), UnitResource::class],
            'store_sections' => [StoreSection::query(), StoreSectionResource::class],
            'tags' => [Tag::query(), TagResource::class],
            'tools' => [Tool::query()->withCount('recipes'), ToolResource::class],
            'stores' => [Store::query()->withCount('offers'), StoreResource::class],
            'products' => [
                Product::query()->with(['storeSection', 'aliases', 'conversions.unit', 'offers.store'])->withCount('ingredients'),
                ProductResource::class,
            ],
            'recipes' => [
                Recipe::query()->withMarksOf($user)->with(['ingredients.product', 'ingredients.unit', 'steps', 'tags', 'tools']),
                RecipeResource::class,
            ],
            'meal_plan_entries' => [MealPlanEntry::query()->with('recipe.tags'), MealPlanEntryResource::class],
            'pantry_items' => [PantryItem::query()->with('product.storeSection'), PantryItemResource::class],
            'shopping_lists' => [ShoppingList::query()->with(['items.product.storeSection', 'items.product.offers.store']), ShoppingListResource::class],
        ];
    }
}
