<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_a_first_sync_returns_everything_and_a_later_one_only_what_changed(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Lasagne']);
        $tool = Tool::create(['name' => 'Backofen']);

        $first = $this->getJson('/api/v1/sync')->assertOk()
            ->assertJsonPath('data.has_more', false)
            ->assertJsonPath('data.changed.recipes.0.title', 'Lasagne')
            ->assertJsonPath('data.changed.tools.0.name', 'Backofen')
            ->assertJsonPath('data.household.default_servings', 2)
            ->assertJsonPath('data.peer_key', fn (string $key) => strlen($key) === 48)
            ->json('data.version');

        $this->getJson("/api/v1/sync?since={$first}")
            ->assertJsonPath('data.version', $first)
            ->assertJsonCount(0, 'data.changed.recipes')
            ->assertJsonCount(0, 'data.changed.tools');

        $tool->update(['name' => 'Ofen']);
        $recipe->delete();

        $this->getJson("/api/v1/sync?since={$first}")
            ->assertJsonCount(0, 'data.changed.recipes')
            ->assertJsonPath('data.changed.tools.0.name', 'Ofen')
            ->assertJsonPath('data.deleted.recipes', [$recipe->id]);
    }

    public function test_a_change_to_a_part_counts_as_a_change_to_the_record(): void
    {
        $recipe = Recipe::factory()->create();
        $product = Product::factory()->create();
        $list = ShoppingList::create(['starts_on' => '2026-11-16', 'ends_on' => '2026-11-22']);
        $since = $this->getJson('/api/v1/sync')->json('data.version');

        $recipe->steps()->create(['position' => 1, 'text' => 'Rühren.']);
        $product->aliases()->create(['name' => 'Alias']);
        $item = $list->items()->create(['label' => 'Blumen', 'is_manual' => true]);

        $changed = $this->getJson("/api/v1/sync?since={$since}")->json('data.changed');

        $this->assertSame([$recipe->id], array_column($changed['recipes'], 'id'));
        $this->assertSame([$product->id], array_column($changed['products'], 'id'));
        $this->assertSame('Blumen', $changed['shopping_lists'][0]['items'][0]['label']);

        $since = $this->getJson('/api/v1/sync')->json('data.version');
        $item->delete();

        $this->getJson("/api/v1/sync?since={$since}")->assertJsonCount(0, 'data.changed.shopping_lists.0.items');
    }

    public function test_a_large_backlog_arrives_in_parts_without_gaps(): void
    {
        Recipe::factory()->count(5)->create();
        Tool::create(['name' => 'Topf']);
        $seen = [];
        $since = 0;

        do {
            $data = $this->getJson("/api/v1/sync?since={$since}&limit=2")->assertOk()->json('data');
            $seen = [...$seen, ...array_column($data['changed']['recipes'], 'id')];
            $this->assertGreaterThan($since, $data['version']);
            $since = $data['version'];
        } while ($data['has_more']);

        $this->assertEqualsCanonicalizing(Recipe::pluck('id')->all(), $seen);
        $this->assertCount(5, $seen);
    }

    public function test_a_repeated_request_is_answered_without_running_again(): void
    {
        $headers = ['Idempotency-Key' => 'b7f6c1d2'];

        $first = $this->postJson('/api/v1/tools', ['name' => 'Wok'], $headers)->assertCreated()->json();
        $second = $this->postJson('/api/v1/tools', ['name' => 'Wok'], $headers)->assertCreated()->json();

        $this->assertSame($first, $second);
        $this->assertSame(1, Tool::count());
    }

    public function test_the_later_action_wins(): void
    {
        $tool = Tool::create(['name' => 'Topf']);
        $earlier = now()->subHour()->toIso8601String();
        $evenEarlier = now()->subHours(2)->toIso8601String();

        $this->patchJson("/api/v1/tools/{$tool->id}", ['name' => 'Großer Topf'], ['X-Client-Time' => $earlier])
            ->assertStatus(409)
            ->assertJsonPath('code', 'stale');

        $tool->newQuery()->toBase()->update(['updated_at' => now()->subDay()]);

        $this->patchJson("/api/v1/tools/{$tool->id}", ['name' => 'Großer Topf'], ['X-Client-Time' => $earlier])->assertOk();
        $this->patchJson("/api/v1/tools/{$tool->id}", ['name' => 'Kleiner Topf'], ['X-Client-Time' => $evenEarlier])->assertStatus(409);
        $this->patchJson("/api/v1/tools/{$tool->id}", ['name' => 'Bräter'])->assertOk();

        $this->assertSame('Bräter', $tool->refresh()->name);
    }
}
