<?php

namespace Tests\Feature;

use App\Enums\UnitKind;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Unit;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CupUnitMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string MIGRATION = 'database/migrations/2026_11_20_000001_add_cup_unit.php';

    public function test_a_cup_the_household_added_is_merged_into_the_built_in_one(): void
    {
        $this->seed(UnitSeeder::class);
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
        $cup = Unit::firstWhere('key', 'cup');

        $own = Unit::create(['code' => 'Tasse', 'name' => 'Tasse', 'plural_name' => 'Tassen', 'kind' => UnitKind::ProductSpecific, 'mealie_unit_id' => '3f1c2c51-6a59-4a63-9d7e-0c6b3f0f6a11']);
        $cups = Unit::create(['code' => 'cups', 'kind' => UnitKind::ProductSpecific]);
        $mug = Unit::create(['code' => 'Becherchen', 'kind' => UnitKind::ProductSpecific]);
        $flour = Product::factory()->create();
        $rice = Product::factory()->create();
        $recipe = Recipe::factory()->create();
        DB::table('recipe_ingredients')->insert([
            ['recipe_id' => $recipe->id, 'position' => 1, 'product_id' => $flour->id, 'quantity' => 2, 'unit_id' => $own->id],
            ['recipe_id' => $recipe->id, 'position' => 2, 'product_id' => $rice->id, 'quantity' => 1, 'unit_id' => $cups->id],
        ]);
        DB::table('product_conversions')->insert([
            ['product_id' => $flour->id, 'unit_id' => $own->id, 'amount_in_base' => 120, 'source' => 'manual'],
            ['product_id' => $rice->id, 'unit_id' => $cup->id, 'amount_in_base' => 180, 'source' => 'manual'],
            ['product_id' => $rice->id, 'unit_id' => $cups->id, 'amount_in_base' => 999, 'source' => 'manual'],
        ]);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $this->assertSame([$cup->id, $cup->id], DB::table('recipe_ingredients')->orderBy('position')->pluck('unit_id')->all());
        $this->assertEquals(
            [$flour->id => 120, $rice->id => 180],
            DB::table('product_conversions')->where('unit_id', $cup->id)->pluck('amount_in_base', 'product_id')->map(fn ($amount) => (float) $amount)->all(),
        );
        $this->assertSame(0, Unit::whereIn('id', [$own->id, $cups->id])->count());
        $this->assertTrue(Unit::whereKey($mug->id)->exists());
        $this->assertSame('3f1c2c51-6a59-4a63-9d7e-0c6b3f0f6a11', $cup->refresh()->mealie_unit_id);
    }

    public function test_a_new_installation_gets_the_cup_from_the_seeder(): void
    {
        $this->assertSame(0, Unit::count());

        $this->seed(UnitSeeder::class);

        $this->assertSame(['tablespoon', 'teaspoon', 'cup', 'pinch'], Unit::orderBy('id')->pluck('key')->slice(4, 4)->values()->all());
    }
}
