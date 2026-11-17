<?php

namespace Tests\Feature\Planning;

use App\Enums\MealType;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
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
        $this->assertSame([['package_id' => $cream->packages->first()->id, 'label' => 'Packung', 'amount' => 200, 'count' => 2]], $item['packages']);
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

        $this->postJson("/api/v1/shopping-lists/{$list['id']}/items", ['label' => 'Spülmittel'])->assertCreated();
        $this->patchJson("/api/v1/shopping-list-items/{$list['items'][0]['id']}", ['checked' => true])
            ->assertOk()
            ->assertJsonPath('data.checked_at', fn (?string $checkedAt) => $checkedAt !== null);
        $this->plan('2026-11-17', $this->recipe('Pfanne', [[$rice, 400, 'gram']]));

        $refreshed = $this->createList()->assertOk()->json('data');

        $this->assertSame($list['id'], $refreshed['id']);
        $this->assertCount(2, $refreshed['items']);
        $this->assertEquals(1000, $refreshed['items'][0]['purchase_amount']);
        $this->assertNotNull($refreshed['items'][0]['checked_at']);
        $this->assertSame('Spülmittel', $refreshed['items'][1]['label']);
        $this->getJson('/api/v1/shopping-lists/current')->assertJsonPath('data.id', $list['id']);
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
     * @return TestResponse<JsonResponse>
     */
    private function createList(): TestResponse
    {
        return $this->postJson('/api/v1/shopping-lists', ['from' => '2026-11-16', 'to' => '2026-11-22']);
    }
}
