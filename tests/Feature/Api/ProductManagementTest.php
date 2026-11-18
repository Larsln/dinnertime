<?php

namespace Tests\Feature\Api;

use App\Enums\ReviewStatus;
use App\Jobs\EnrichProduct;
use App\Models\PantryItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_the_review_list_shows_everything_that_awaits_confirmation(): void
    {
        $this->product('Bestätigt');
        $new = $this->product('Neu', attributes: ['status' => ReviewStatus::Suggested]);
        $withConversion = $this->product('Mehl');
        $this->convert($withConversion, 'tablespoon', 10, ReviewStatus::Suggested);
        $this->recipe('Kuchen', [[$withConversion, 2, 'tablespoon']]);

        $this->getJson('/api/v1/review')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Mehl')
            ->assertJsonPath('data.0.recipe_count', 1)
            ->assertJsonPath('data.0.conversions.0.status', 'suggested')
            ->assertJsonPath('data.1.name', 'Neu');

        $this->postJson("/api/v1/products/{$withConversion->id}/confirm")->assertOk();
        $this->postJson("/api/v1/products/{$new->id}/confirm")->assertJsonPath('data.status', 'confirmed');

        $this->getJson('/api/v1/review')->assertJsonCount(0, 'data');
    }

    public function test_it_creates_and_updates_products(): void
    {
        $id = $this->postJson('/api/v1/products', ['name' => 'Hafermilch', 'base_unit' => 'ml'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.is_pantry_staple', false)
            ->json('data.id');

        Queue::assertPushed(EnrichProduct::class);

        $this->patchJson("/api/v1/products/{$id}", ['is_pantry_staple' => true, 'shelf_life_opened_days' => 5])
            ->assertOk()
            ->assertJsonPath('data.is_pantry_staple', true)
            ->assertJsonPath('data.base_unit', 'ml');

        $this->postJson('/api/v1/products', ['name' => 'Hafermilch'])->assertUnprocessable();
        $this->patchJson("/api/v1/products/{$id}", ['name' => 'Hafermilch', 'base_unit' => 'liter'])->assertUnprocessable();
    }

    public function test_user_values_for_conversions_and_packages_count_as_confirmed(): void
    {
        $flour = $this->product('Mehl', [1000]);
        $spoon = Unit::firstWhere('key', 'tablespoon');
        $this->convert($flour, 'tablespoon', 8, ReviewStatus::Suggested);

        $this->putJson("/api/v1/products/{$flour->id}/conversions/{$spoon->id}", ['amount_in_base' => 10])
            ->assertOk()
            ->assertJsonPath('data.amount_in_base', 10)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.source', 'user');

        $piece = Unit::firstWhere('key', 'piece');
        $this->putJson("/api/v1/products/{$flour->id}/conversions/{$piece->id}", ['amount_in_base' => null])
            ->assertCreated()
            ->assertJsonPath('data.amount_in_base', null)
            ->assertJsonPath('data.status', 'confirmed');
        $this->putJson("/api/v1/products/{$flour->id}/conversions/{$piece->id}", [])->assertUnprocessable();
        $this->deleteJson("/api/v1/products/{$flour->id}/conversions/{$piece->id}")->assertNoContent();

        $packageId = $this->postJson("/api/v1/products/{$flour->id}/packages", ['label' => 'Tüte', 'amount_in_base' => 500])
            ->assertCreated()
            ->json('data.id');
        $this->postJson("/api/v1/products/{$flour->id}/packages", ['label' => 'Tüte', 'amount_in_base' => 1000])->assertUnprocessable();
        $this->patchJson("/api/v1/product-packages/{$packageId}", ['amount_in_base' => 405])->assertJsonPath('data.amount_in_base', 405);
        $this->patchJson("/api/v1/product-packages/{$packageId}", ['amount_in_base' => 1000])->assertUnprocessable();

        $this->deleteJson("/api/v1/product-packages/{$packageId}")->assertNoContent();
        $this->deleteJson("/api/v1/products/{$flour->id}/conversions/{$spoon->id}")->assertNoContent();
        $this->assertSame(0, $flour->conversions()->count());
        $this->assertSame(1, $flour->packages()->count());
    }

    public function test_it_manages_aliases(): void
    {
        $carrot = $this->product('Karotte');
        $this->product('Zwiebel');

        $this->postJson("/api/v1/products/{$carrot->id}/aliases", ['name' => 'Möhre'])
            ->assertOk()
            ->assertJsonPath('data.aliases', ['Möhre']);
        $this->postJson("/api/v1/products/{$carrot->id}/aliases", ['name' => 'Möhre'])->assertUnprocessable();
        $this->postJson("/api/v1/products/{$carrot->id}/aliases", ['name' => 'Zwiebel'])->assertUnprocessable();

        $this->deleteJson('/api/v1/product-aliases/'.$carrot->aliases()->value('id'))->assertNoContent();
    }

    public function test_it_merges_a_duplicate_into_another_product(): void
    {
        $target = $this->product('Sojasoße');
        $duplicate = $this->product('Sojasauce', [150]);
        $duplicate->aliases()->create(['name' => 'Shoyu']);
        $recipe = $this->recipe('Wok', [[$duplicate, 2, 'tablespoon']]);
        PantryItem::create(['product_id' => $duplicate->id, 'amount' => 80]);

        $this->postJson("/api/v1/products/{$duplicate->id}/merge", ['into' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sojasoße')
            ->assertJsonPath('data.aliases', ['Shoyu', 'Sojasauce']);

        $this->assertNull(Product::find($duplicate->id));
        $this->assertSame($target->id, $recipe->ingredients()->value('product_id'));
        $this->assertSame($target->id, PantryItem::value('product_id'));
        $this->postJson("/api/v1/products/{$target->id}/merge", ['into' => $target->id])->assertUnprocessable();
    }

    public function test_it_only_deletes_unused_products(): void
    {
        $used = $this->product('Mehl');
        $unused = $this->product('Hefe');
        $this->recipe('Brot', [[$used, 500, 'gram']]);

        $this->deleteJson("/api/v1/products/{$used->id}")->assertConflict();
        $this->deleteJson("/api/v1/products/{$unused->id}")->assertNoContent();
    }
}
