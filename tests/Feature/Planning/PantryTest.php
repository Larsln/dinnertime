<?php

namespace Tests\Feature\Planning;

use App\Models\PantryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PantryTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_derives_the_expiry_from_the_shelf_life(): void
    {
        $cream = $this->product('Sahne', attributes: ['shelf_life_opened_days' => 4]);

        $this->postJson('/api/v1/pantry', ['product_id' => $cream->id, 'amount' => 50, 'opened_on' => '2026-11-16'])
            ->assertCreated()
            ->assertJsonPath('data.product.name', 'Sahne')
            ->assertJsonPath('data.expires_on', '2026-11-20');

        $this->postJson('/api/v1/pantry', ['product_id' => $cream->id, 'amount' => 50, 'opened_on' => '2026-11-16', 'expires_on' => '2026-11-17'])
            ->assertJsonPath('data.expires_on', '2026-11-17');

        $this->postJson('/api/v1/pantry', ['product_id' => $this->product('Reis')->id, 'amount' => 300])
            ->assertJsonPath('data.expires_on', null)
            ->assertJsonPath('data.opened_on', today()->toDateString());
    }

    public function test_it_lists_the_soonest_expiry_first_and_updates_items(): void
    {
        $rice = PantryItem::create(['product_id' => $this->product('Reis')->id, 'amount' => 300]);
        PantryItem::create(['product_id' => $this->product('Sahne')->id, 'amount' => 50, 'expires_on' => '2026-11-20']);

        $this->getJson('/api/v1/pantry')
            ->assertJsonPath('data.0.product.name', 'Sahne')
            ->assertJsonPath('data.1.product.name', 'Reis');

        $this->patchJson("/api/v1/pantry/{$rice->id}", ['amount' => 120.5])->assertJsonPath('data.amount', 120.5);
        $this->patchJson("/api/v1/pantry/{$rice->id}", ['amount' => 0])->assertUnprocessable();
        $this->deleteJson("/api/v1/pantry/{$rice->id}")->assertNoContent();
        $this->assertSame(1, PantryItem::count());
    }
}
