<?php

namespace Tests\Feature\Api;

use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use App\Models\Product;
use App\Models\StoreSection;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_finds_products_by_name_or_alias(): void
    {
        $carrot = Product::factory()->create(['name' => 'Karotte']);
        $carrot->aliases()->create(['name' => 'Möhre']);
        Product::factory()->create(['name' => 'Zwiebel']);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/products?search=möh')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Karotte')
            ->assertJsonPath('data.0.aliases', ['Möhre']);
    }

    public function test_it_shows_a_product_with_conversions_and_offers(): void
    {
        $flour = Product::factory()->create(['name' => 'Mehl']);
        $flour->conversions()->create([
            'unit_id' => Unit::factory()->create(['code' => 'EL'])->id,
            'amount_in_base' => 10,
            'status' => ReviewStatus::Suggested,
            'source' => ValueSource::Ai,
        ]);
        $flour->offers()->create(['amount' => 1, 'unit' => 'kg']);

        $this->getJson("/api/v1/products/{$flour->id}")
            ->assertOk()
            ->assertJsonPath('data.base_unit', 'g')
            ->assertJsonPath('data.conversions.0.unit.code', 'EL')
            ->assertJsonPath('data.conversions.0.status', 'suggested')
            ->assertJsonPath('data.offers.0.amount', 1)
            ->assertJsonPath('data.offers.0.store', null);
    }

    public function test_it_lists_reference_data_in_the_requested_language(): void
    {
        $this->seed();
        StoreSection::factory()->create(['name' => 'Hofladen', 'position' => 12]);

        $this->getJson('/api/v1/store-sections', ['Accept-Language' => 'de-DE,de;q=0.9'])
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Obst & Gemüse')
            ->assertJsonPath('data.11.name', 'Hofladen');

        $this->getJson('/api/v1/store-sections', ['Accept-Language' => 'en-US'])
            ->assertJsonPath('data.0.key', 'produce')
            ->assertJsonPath('data.0.name', 'Fruit & Vegetables')
            ->assertJsonPath('data.11.name', 'Hofladen');

        $this->getJson('/api/v1/units', ['Accept-Language' => 'de'])
            ->assertJsonCount(14, 'data')
            ->assertJsonPath('data.4.code', 'EL')
            ->assertJsonPath('data.6.plural_name', 'Tassen')
            ->assertJsonPath('data.7.plural_name', 'Prisen');

        $this->getJson('/api/v1/units', ['Accept-Language' => 'en'])->assertJsonPath('data.4.code', 'tbsp');
        $this->getJson('/api/v1/tags', ['Accept-Language' => 'en'])->assertJsonCount(20, 'data');
    }

    public function test_it_falls_back_to_the_instance_locale_for_unsupported_languages(): void
    {
        $this->seed();
        app()->setLocale('de');

        $this->getJson('/api/v1/store-sections', ['Accept-Language' => 'fr-FR,es;q=0.8'])
            ->assertJsonPath('data.0.name', 'Obst & Gemüse');
    }
}
