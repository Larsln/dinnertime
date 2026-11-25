<?php

namespace Tests\Feature\Planning;

use App\Enums\MealType;
use App\Enums\ReviewStatus;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
use App\Models\Store;
use App\Models\StoreSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_buys_packages_and_reports_where_the_rest_is_used(): void
    {
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 4]);
        $this->plan('2026-11-16', $this->recipe('Lasagne', [[$cream, 250, 'gram']]));
        $this->plan('2026-11-19', $this->recipe('Suppe', [[$cream, 100, 'gram']]));

        $item = $this->createList()->assertCreated()->json('data.items.0');

        $this->assertSame('Sahne', $item['product']['name']);
        $this->assertEquals(350, $item['needed_amount']);
        $this->assertEquals(400, $item['purchase_amount']);
        $this->assertEquals(50, $item['leftover_amount']);
        $this->assertEquals(0, $item['wasted_amount']);
        $this->assertSame([['package_id' => $cream->offers->first()->id, 'label' => '', 'amount' => 200, 'count' => 2]], $item['packages']);
        $this->assertSame(['2026-11-16', '2026-11-19'], array_column($item['uses'], 'date'));
        $this->assertSame(['Lasagne', 'Suppe'], array_column($item['uses'], 'title'));
    }

    public function test_it_buys_again_when_an_opened_package_would_expire(): void
    {
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 2]);
        $this->plan('2026-11-16', $this->recipe('Lasagne', [[$cream, 150, 'gram']]));
        $this->plan('2026-11-20', $this->recipe('Suppe', [[$cream, 100, 'gram']]));

        $item = $this->createList()->json('data.items.0');

        $this->assertEquals(400, $item['purchase_amount']);
        $this->assertEquals(50, $item['wasted_amount']);
        $this->assertEquals(100, $item['leftover_amount']);
    }

    public function test_it_scales_servings_and_subtracts_the_pantry(): void
    {
        $rice = $this->product('Reis', [500]);
        $this->plan('2026-11-16', $this->recipe('Curry', [[$rice, 150, 'gram']]), servings: 4);
        PantryItem::create(['product_id' => $rice->id, 'amount' => 120]);

        $item = $this->createList()->json('data.items.0');

        $this->assertEquals(300, $item['needed_amount']);
        $this->assertEquals(500, $item['purchase_amount']);
        $this->assertEquals(320, $item['leftover_amount']);
    }

    public function test_it_skips_staples_and_products_the_pantry_covers(): void
    {
        $salt = $this->product('Salz', [500], ['is_pantry_staple' => true]);
        $rice = $this->product('Reis', [500]);
        $this->plan('2026-11-16', $this->recipe('Reis', [[$salt, 5, 'gram'], [$rice, 150, 'gram']]));
        PantryItem::create(['product_id' => $rice->id, 'amount' => 500]);

        $this->createList()->assertJsonCount(0, 'data.items');
    }

    public function test_it_orders_by_store_section_and_lists_unresolved_lines_last(): void
    {
        $sections = StoreSection::pluck('id', 'key');
        $milk = $this->product('Milch', attributes: ['store_section_id' => $sections['chilled']]);
        $apple = $this->product('Apfel', attributes: ['store_section_id' => $sections['produce']]);
        $pepper = $this->product('Pfeffer');
        $recipe = $this->recipe('Müsli', [[$milk, 200, 'gram'], [$apple, 100, 'gram'], [$pepper, null]]);
        $recipe->ingredients()->create(['position' => 4, 'raw_text' => 'etwas Zimt']);
        $this->plan('2026-11-16', $recipe);

        $items = $this->createList()->json('data.items');

        $this->assertSame(['Apfel', 'Milch', 'Pfeffer', null], array_map(fn (array $item) => $item['product']['name'] ?? null, $items));
        $this->assertSame('etwas Zimt', $items[3]['label']);
        $this->assertNull($items[2]['needed_amount']);
    }

    public function test_it_lists_amounts_that_cannot_be_converted_in_the_recipe_unit(): void
    {
        $apple = $this->product('Apfel', [1000]);
        $this->plan('2026-11-16', $this->recipe('Kuchen', [[$apple, 3], [$apple, 200, 'gram']]));
        $this->plan('2026-11-17', $this->recipe('Kompott', [[$apple, 2], [$apple, 1, 'bunch']]), servings: 4);

        $item = $this->createList()->json('data.items.0', []);

        $this->assertEquals(200, $item['needed_amount']);
        $this->assertEquals(1000, $item['purchase_amount']);
        $this->assertEqualsCanonicalizing(
            [['quantity' => 7, 'unit_code' => 'pc'], ['quantity' => 2, 'unit_code' => 'bunch']],
            $item['extra_amounts'],
        );
    }

    public function test_a_product_needed_only_in_an_unconvertible_unit_is_still_listed(): void
    {
        $lemon = $this->product('Zitrone');
        $this->plan('2026-11-16', $this->recipe('Limonade', [[$lemon, 2]]));

        $this->createList()
            ->assertJsonPath('data.items.0.product.name', 'Zitrone')
            ->assertJsonPath('data.items.0.purchase_amount', null)
            ->assertJsonPath('data.items.0.extra_amounts.0.quantity', 2);
    }

    public function test_it_flags_amounts_based_on_unconfirmed_conversions(): void
    {
        $cream = $this->product('Sahne', [200]);
        $this->plan('2026-11-16', $this->recipe('Soße', [[$cream, 100, 'milliliter']]));

        $this->createList()->assertJsonPath('data.items.0.is_approximate', true);
    }

    public function test_a_refresh_keeps_manual_and_checked_positions(): void
    {
        $rice = $this->product('Reis', [500]);
        $this->plan('2026-11-16', $this->recipe('Curry', [[$rice, 150, 'gram']]));
        $list = $this->createList()->json('data');

        $this->postJson("/api/v1/shopping-lists/{$list['id']}/items", ['label' => 'Spülmittel'])
            ->assertCreated()
            ->assertJsonPath('data.is_manual', true)
            ->assertJsonPath('data.is_approximate', false)
            ->assertJsonPath('data.leftover_amount', 0);
        $this->patchJson("/api/v1/shopping-list-items/{$list['items'][0]['id']}", ['checked' => true])
            ->assertOk()
            ->assertJsonPath('data.checked_at', fn (?string $checkedAt) => $checkedAt !== null);
        $this->plan('2026-11-17', $this->recipe('Pfanne', [[$rice, 400, 'gram']]));

        $refreshed = $this->createList()->assertOk()->json('data');
        $rice = collect($refreshed['items'])->whereNotNull('product');

        $this->assertSame($list['id'], $refreshed['id']);
        $this->assertCount(3, $refreshed['items']);
        $this->assertEquals([500, 500], $rice->pluck('purchase_amount')->all());
        $this->assertSame(1, $rice->whereNotNull('checked_at')->count());
        $this->assertSame($list['items'][0]['id'], $rice->whereNotNull('checked_at')->value('id'));
        $this->assertSame('Spülmittel', $refreshed['items'][2]['label']);
        $this->getJson('/api/v1/shopping-lists/current')->assertJsonPath('data.id', $list['id']);
    }

    public function test_bought_positions_cover_what_they_can_and_survive_a_smaller_plan(): void
    {
        $rice = $this->product('Reis', [500]);
        $curry = $this->plan('2026-11-16', $this->recipe('Curry', [[$rice, 150, 'gram']]));
        $item = $this->createList()->json('data.items.0');
        $this->patchJson("/api/v1/shopping-list-items/{$item['id']}", ['checked' => true])->assertOk();

        $this->plan('2026-11-17', $this->recipe('Pfanne', [[$rice, 200, 'gram']]));
        $this->assertCount(1, $this->createList()->json('data.items'));

        $curry->delete();
        $this->createList()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $item['id']);
    }

    public function test_products_bought_as_another_share_its_position(): void
    {
        $onion = $this->product('Zwiebel', [1], ['base_unit' => 'piece']);
        $red = $this->product('Rote Zwiebel', [], ['base_unit' => 'piece', 'buy_as_id' => $onion->id]);
        $pickles = $this->product('Saure Gurken', [330]);
        $brine = $this->product('Gurkenwasser', [], ['base_unit' => 'ml', 'buy_as_id' => $pickles->id]);
        $this->plan('2026-11-16', $this->recipe('Salat', [[$red, 1], [$brine, 50, 'milliliter']]));
        $this->plan('2026-11-17', $this->recipe('Suppe', [[$onion, 2]]));

        $items = collect($this->createList()->json('data.items'))->keyBy('product.name');

        $this->assertCount(2, $items);
        $this->assertEquals(2, $items['Zwiebel']['purchase_amount']);
        $this->assertSame(['Rote Zwiebel'], array_column($items['Zwiebel']['variants'], 'name'));
        $this->assertEquals(1, $items['Zwiebel']['variants'][0]['needed_amount']);
        $this->assertSame('piece', $items['Zwiebel']['variants'][0]['base_unit']);
        $this->assertNull($items['Saure Gurken']['purchase_amount']);
        $this->assertSame('Gurkenwasser', $items['Saure Gurken']['variants'][0]['name']);
        $this->assertSame('Salat', $items['Saure Gurken']['variants'][0]['uses'][0]['title']);
        $this->assertSame([], $items['Saure Gurken']['packages']);
    }

    public function test_running_lists_follow_when_a_product_is_bought_as_another(): void
    {
        $this->travelTo('2026-11-18');
        $onion = $this->product('Zwiebel', [1], ['base_unit' => 'piece']);
        $red = $this->product('Rote Zwiebel', [1], ['base_unit' => 'piece']);
        $this->plan('2026-11-16', $this->recipe('Salat', [[$red, 1], [$onion, 1]]));
        $listId = $this->createList()->json('data.id');

        $this->patchJson("/api/v1/products/{$red->id}", ['buy_as_id' => $onion->id])->assertOk();

        $this->assertSame(['Zwiebel'], $this->productNames($listId));
    }

    public function test_the_list_follows_changes_to_the_plan(): void
    {
        $rice = $this->product('Reis', [500]);
        $cream = $this->product('Sahne', [200]);
        $curry = $this->postJson('/api/v1/meal-plan', $this->entry('2026-11-16', $this->recipe('Curry', [[$rice, 150, 'gram']])))->json('data.id');
        $listId = $this->createList()->json('data.id');
        $soup = $this->recipe('Suppe', [[$cream, 100, 'gram']]);

        $soupEntry = $this->postJson('/api/v1/meal-plan', $this->entry('2026-11-18', $soup))->json('data.id');
        $this->assertSame(['Reis', 'Sahne'], $this->productNames($listId));

        $this->patchJson("/api/v1/meal-plan/{$soupEntry}", ['date' => '2026-11-24'])->assertOk();
        $this->assertSame(['Reis'], $this->productNames($listId));

        $this->postJson('/api/v1/meal-plan/shift', ['from' => '2026-11-16', 'days' => 7])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([], $this->productNames($listId));

        $this->postJson('/api/v1/meal-plan/shift', ['from' => '2026-11-23', 'days' => -7])->assertOk();
        $this->assertSame(['Reis'], $this->productNames($listId));

        $this->deleteJson("/api/v1/meal-plan/{$curry}")->assertNoContent();
        $this->assertSame([], $this->productNames($listId));
    }

    public function test_a_list_whose_leftovers_were_stocked_stays_as_it_is(): void
    {
        $rice = $this->product('Reis', [500]);
        $this->plan('2026-11-16', $this->recipe('Curry', [[$rice, 150, 'gram']]));
        $listId = $this->createList()->json('data.id');
        $this->postJson("/api/v1/shopping-lists/{$listId}/stock-leftovers")->assertOk();

        $this->postJson('/api/v1/meal-plan', $this->entry('2026-11-17', $this->recipe('Suppe', [[$this->product('Sahne', [200]), 100, 'gram']])))->assertCreated();

        $this->assertSame(['Reis'], $this->productNames($listId));
    }

    public function test_a_manual_position_is_linked_to_its_product(): void
    {
        $rice = $this->product('Reis', [500]);
        $milk = $this->product('Milch', [1000]);
        $this->plan('2026-11-16', $this->recipe('Curry', [[$rice, 150, 'gram']]));
        $list = $this->createList()->json('data');
        $items = "/api/v1/shopping-lists/{$list['id']}/items";

        $id = $this->postJson($items, ['product_id' => $milk->id])
            ->assertCreated()
            ->assertJsonPath('data.is_manual', true)
            ->assertJsonPath('data.label', null)
            ->assertJsonPath('data.product.id', $milk->id)
            ->json('data.id');
        $this->patchJson("/api/v1/shopping-list-items/{$id}", ['checked' => true])->assertOk();

        $this->postJson($items, ['label' => 'milch'])
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.checked_at', null);
        $this->postJson($items, ['label' => 'Geschenkpapier'])->assertCreated()->assertJsonPath('data.product', null);
        $this->postJson($items, [])->assertUnprocessable();
        $this->postJson($items, ['product_id' => 0])->assertUnprocessable();

        $this->assertCount(3, $this->createList()->json('data.items'));
    }

    public function test_it_buys_in_the_sizes_of_preferred_stores_first(): void
    {
        $flour = $this->product('Mehl');
        $lidl = Store::named('Lidl');
        $rewe = Store::named('Rewe');
        $lidl->update(['is_preferred' => true]);
        $atLidl = $flour->offers()->create(['store_id' => $lidl->id, 'amount' => 500, 'unit' => 'g']);
        $flour->offers()->create(['store_id' => $rewe->id, 'amount' => 0.25, 'unit' => 'kg']);
        $flour->offers()->create(['store_id' => $rewe->id, 'amount' => 2, 'unit' => 'l']);
        $this->plan('2026-11-16', $this->recipe('Pfannkuchen', [[$flour, 400, 'gram']]));

        $this->assertSame(
            [['package_id' => $atLidl->id, 'label' => 'Lidl', 'amount' => 500, 'count' => 1]],
            $this->createList()->json('data.items.0.packages'),
        );

        $lidl->update(['is_active' => false]);

        $this->createList()
            ->assertJsonCount(1, 'data.items.0.packages')
            ->assertJsonPath('data.items.0.packages.0.label', 'Rewe')
            ->assertJsonPath('data.items.0.packages.0.count', 2);

        $flour->offers()->create(['amount' => 1000, 'unit' => 'g']);

        $this->createList()->assertJsonPath('data.items.0.packages.0.label', '')->assertJsonPath('data.items.0.packages.0.amount', 1000);
    }

    public function test_it_moves_leftovers_to_the_pantry_once(): void
    {
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 4]);
        $this->plan('2026-11-16', $this->recipe('Lasagne', [[$cream, 150, 'gram']]));
        PantryItem::create(['product_id' => $cream->id, 'amount' => 20]);
        $listId = $this->createList()->json('data.id');

        $this->postJson("/api/v1/shopping-lists/{$listId}/stock-leftovers")->assertOk();

        $stock = PantryItem::sole();
        $this->assertSame(70.0, $stock->amount);
        $this->assertSame('2026-11-16', $stock->opened_on->toDateString());
        $this->assertSame('2026-11-20', $stock->expires_on->toDateString());
        $this->postJson("/api/v1/shopping-lists/{$listId}/stock-leftovers")->assertConflict();
    }

    public function test_a_skipped_position_stays_off_the_list_and_out_of_the_pantry(): void
    {
        $rice = $this->product('Reis', [500]);
        $cream = $this->product('Sahne', [200]);
        $this->plan('2026-11-16', $this->recipe('Curry', [[$rice, 150, 'gram'], [$cream, 100, 'gram']]));
        PantryItem::create(['product_id' => $rice->id, 'amount' => 20]);
        $list = $this->createList()->json('data');
        $riceItem = collect($list['items'])->firstWhere('product.name', 'Reis');

        $this->patchJson("/api/v1/shopping-list-items/{$riceItem['id']}", ['skipped' => true])
            ->assertOk()
            ->assertJsonPath('data.checked_at', null)
            ->assertJsonPath('data.skipped_at', fn (?string $skippedAt) => $skippedAt !== null);
        $this->plan('2026-11-17', $this->recipe('Pfanne', [[$rice, 400, 'gram']]));

        $this->createList()->assertJsonCount(2, 'data.items');
        $this->postJson("/api/v1/shopping-lists/{$list['id']}/stock-leftovers")->assertOk();
        $this->assertSame(20.0, PantryItem::where('product_id', $rice->id)->sole()->amount);
        $this->assertSame(100.0, PantryItem::where('product_id', $cream->id)->sole()->amount);
    }

    public function test_a_skipped_position_can_be_opened_again(): void
    {
        $rice = $this->product('Reis', [500]);
        $this->plan('2026-11-16', $this->recipe('Curry', [[$rice, 150, 'gram']]));
        $item = $this->createList()->json('data.items.0');
        $this->patchJson("/api/v1/shopping-list-items/{$item['id']}", ['skipped' => true])->assertOk();

        $this->patchJson("/api/v1/shopping-list-items/{$item['id']}", ['skipped' => false])->assertJsonPath('data.skipped_at', null);

        $this->createList()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $item['id']);
    }

    public function test_a_substitute_settles_a_position_without_stocking_its_product(): void
    {
        $tortilla = $this->product('Tortilla', [400]);
        $wraps = $this->product('Wraps');
        $this->plan('2026-11-16', $this->recipe('Burrito', [[$tortilla, 150, 'gram']]));
        $list = $this->createList()->json('data');
        $id = $list['items'][0]['id'];

        $this->patchJson("/api/v1/shopping-list-items/{$id}", ['checked' => true, 'substitute_product_id' => $wraps->id])
            ->assertOk()
            ->assertJsonPath('data.substitute', ['product_id' => $wraps->id, 'name' => 'Wraps'])
            ->assertJsonPath('data.checked_at', fn (?string $checkedAt) => $checkedAt !== null);
        $this->plan('2026-11-17', $this->recipe('Quesadilla', [[$tortilla, 300, 'gram']]));

        $this->createList()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.substitute.name', 'Wraps');
        $this->postJson("/api/v1/shopping-lists/{$list['id']}/stock-leftovers")->assertOk();
        $this->assertSame(0, PantryItem::count());
    }

    public function test_a_substitute_can_be_free_text_and_is_forgotten_when_the_position_opens_again(): void
    {
        $tortilla = $this->product('Tortilla', [400]);
        $this->product('Wraps')->aliases()->create(['name' => 'Weizenwraps']);
        $this->plan('2026-11-16', $this->recipe('Burrito', [[$tortilla, 150, 'gram']]));
        $id = $this->createList()->json('data.items.0.id');

        $this->patchJson("/api/v1/shopping-list-items/{$id}", ['checked' => true, 'substitute_label' => 'Fladenbrot vom Bäcker'])
            ->assertJsonPath('data.substitute', ['product_id' => null, 'name' => 'Fladenbrot vom Bäcker']);
        $this->patchJson("/api/v1/shopping-list-items/{$id}", ['substitute_label' => 'Weizenwraps'])
            ->assertJsonPath('data.substitute.name', 'Wraps');
        $this->patchJson("/api/v1/shopping-list-items/{$id}", ['checked' => false])
            ->assertJsonPath('data.substitute', null);
    }

    public function test_suggested_staples_are_shopped_for_until_they_are_confirmed(): void
    {
        $oil = $this->product('Öl', [500], ['is_pantry_staple' => true, 'status' => ReviewStatus::Suggested]);
        $this->plan('2026-11-16', $this->recipe('Pfanne', [[$oil, 20, 'gram']]));

        $this->createList()->assertJsonCount(1, 'data.items');
        $this->postJson("/api/v1/products/{$oil->id}/confirm")->assertOk();

        $this->createList()->assertJsonCount(0, 'data.items');
    }

    public function test_it_validates_the_period(): void
    {
        $this->postJson('/api/v1/shopping-lists', ['from' => '2026-11-16', 'to' => '2026-11-15'])->assertUnprocessable();
        $this->postJson('/api/v1/shopping-lists', ['from' => '2026-11-16', 'to' => '2027-01-16'])->assertUnprocessable();
        $this->getJson('/api/v1/shopping-lists/current')->assertNotFound();
    }

    private function plan(string $date, Recipe $recipe, int $servings = 2): MealPlanEntry
    {
        return MealPlanEntry::create([
            'date' => $date,
            'meal' => MealType::Dinner,
            'recipe_id' => $recipe->id,
            'servings' => $servings,
        ]);
    }

    /**
     * @return array{date: string, meal: string, recipe_id: int, servings: int}
     */
    private function entry(string $date, Recipe $recipe): array
    {
        return ['date' => $date, 'meal' => 'dinner', 'recipe_id' => $recipe->id, 'servings' => 2];
    }

    /**
     * @return list<string>
     */
    private function productNames(int $listId): array
    {
        return array_column($this->getJson("/api/v1/shopping-lists/{$listId}")->json('data.items.*.product'), 'name');
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function createList(): TestResponse
    {
        return $this->postJson('/api/v1/shopping-lists', ['from' => '2026-11-16', 'to' => '2026-11-22']);
    }
}
