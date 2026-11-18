<?php

namespace Tests\Feature;

use App\Enums\Course;
use App\Enums\ReviewStatus;
use App\Enums\SourcePlatform;
use App\Enums\TagType;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StoreSection;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MealieImportTest extends TestCase
{
    use RefreshDatabase;

    private const string LABEL_ID = '11111111-1111-1111-1111-111111111111';

    private const string FOOD_ID = '22222222-2222-2222-2222-222222222222';

    private const string UNIT_ID = '33333333-3333-3333-3333-333333333333';

    private const string RECIPE_ID = '44444444-4444-4444-4444-444444444444';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Storage::fake('public');
        config(['services.mealie' => ['url' => 'https://mealie.test', 'token' => 'secret']]);

        Http::preventStrayRequests();
        Http::fake([
            'mealie.test/api/groups/labels*' => Http::response(['items' => [
                ['id' => self::LABEL_ID, 'name' => 'Kühlregal'],
            ]]),
            'mealie.test/api/units*' => Http::response(['items' => [[
                'id' => self::UNIT_ID,
                'name' => 'Esslöffel',
                'pluralName' => null,
                'abbreviation' => 'EL',
                'pluralAbbreviation' => null,
                'aliases' => [],
            ]]]),
            'mealie.test/api/foods*' => Http::response(['items' => [[
                'id' => self::FOOD_ID,
                'name' => 'Sojasoße',
                'pluralName' => null,
                'labelId' => self::LABEL_ID,
                'aliases' => [['name' => 'Sojasauce']],
            ]]]),
            'mealie.test/api/recipes?*' => Http::response(['items' => [
                ['id' => self::RECIPE_ID, 'slug' => 'hot-honey-chicken-tenders'],
            ]]),
            'mealie.test/api/recipes/hot-honey-chicken-tenders' => Http::response($this->recipe()),
            'mealie.test/api/media/recipes/*' => Http::response('image-bytes'),
        ]);
    }

    public function test_it_imports_catalog_and_recipes(): void
    {
        $this->artisan('mealie:import')->assertSuccessful();

        $product = Product::with('aliases', 'storeSection')->sole();
        $this->assertSame('Sojasoße', $product->name);
        $this->assertSame(ReviewStatus::Suggested, $product->refresh()->status);
        $this->assertSame('chilled', $product->storeSection->key);
        $this->assertSame(['Sojasauce'], $product->aliases->pluck('name')->all());
        $this->assertSame(11, StoreSection::count());
        $this->assertSame(self::UNIT_ID, Unit::firstWhere('key', 'tablespoon')->mealie_unit_id);

        $recipe = Recipe::with('ingredients', 'steps', 'tags', 'tools')->sole();
        $this->assertSame('Hot-Honey Chicken Tenders', $recipe->title);
        $this->assertSame(2, $recipe->servings);
        $this->assertSame('12 Stück', $recipe->yield_text);
        $this->assertSame(15, $recipe->prep_minutes);
        $this->assertSame(SourcePlatform::Tiktok, $recipe->source_platform);
        $this->assertSame('7668426929069903137', $recipe->source_video_id);
        $this->assertSame(['Airfryer'], $recipe->tools->pluck('name')->all());
        $this->assertSame([TagType::Meal], $recipe->tags->pluck('type')->all());
        $this->assertSame(Course::Dessert, $recipe->course);

        [$sauce, $free] = $recipe->ingredients;
        $this->assertSame('Soße', $sauce->section);
        $this->assertSame($product->id, $sauce->product_id);
        $this->assertSame(2.0, $sauce->quantity);
        $this->assertSame('hell', $sauce->note);
        $this->assertTrue($sauce->is_estimated);
        $this->assertSame('Soße', $free->section);
        $this->assertNull($free->product_id);
        $this->assertNull($free->quantity);
        $this->assertSame('etwas Liebe', $free->raw_text);

        $this->assertSame('Marinade', $recipe->steps->first()->title);
        Storage::disk('public')->assertExists($recipe->cover_image_path);
    }

    public function test_it_reports_tags_outside_the_fixed_lists(): void
    {
        $this->artisan('mealie:import')
            ->expectsOutputToContain('Video-KI')
            ->assertSuccessful();
    }

    public function test_it_keeps_local_changes_unless_forced(): void
    {
        $this->artisan('mealie:import')->assertSuccessful();
        Recipe::sole()->update(['title' => 'Edited']);
        Product::sole()->update(['is_pantry_staple' => true, 'store_section_id' => null]);

        $this->artisan('mealie:import')->assertSuccessful();
        $this->assertSame('Edited', Recipe::sole()->title);
        $this->assertNull(Product::sole()->store_section_id);

        $this->artisan('mealie:import --force')->assertSuccessful();
        $this->assertSame('Hot-Honey Chicken Tenders', Recipe::sole()->title);
        $this->assertNotNull(Product::sole()->store_section_id);
        $this->assertTrue(Product::sole()->is_pantry_staple);
        $this->assertCount(2, Recipe::sole()->ingredients);
    }

    public function test_it_fails_without_a_token(): void
    {
        config(['services.mealie.token' => null]);

        $this->artisan('mealie:import')->assertFailed();

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function recipe(): array
    {
        return [
            'id' => self::RECIPE_ID,
            'name' => 'Hot-Honey Chicken Tenders',
            'slug' => 'hot-honey-chicken-tenders',
            'description' => 'Knusprige Tenders mit scharfem Honig.',
            'image' => 'abcd',
            'recipeServings' => 2.0,
            'recipeYieldQuantity' => 12.0,
            'recipeYield' => 'Stück',
            'prepTimeSeconds' => 900,
            'performTimeSeconds' => null,
            'orgURL' => 'https://www.tiktok.com/@koch/video/7668426929069903137',
            'recipeCategory' => [['name' => 'Abendessen'], ['name' => 'Dessert']],
            'tags' => [['name' => 'Abendessen'], ['name' => 'Video-KI']],
            'tools' => [['name' => 'Airfryer']],
            'recipeIngredient' => [
                [
                    'title' => 'Soße',
                    'quantity' => 2.0,
                    'unit' => ['id' => self::UNIT_ID],
                    'food' => ['id' => self::FOOD_ID],
                    'note' => 'hell (Menge geschätzt)',
                    'originalText' => null,
                    'display' => '2 EL Sojasoße hell',
                ],
                [
                    'title' => null,
                    'quantity' => 0.0,
                    'unit' => null,
                    'food' => null,
                    'note' => 'etwas Liebe',
                    'originalText' => 'etwas Liebe',
                    'display' => 'etwas Liebe',
                ],
            ],
            'recipeInstructions' => [
                ['title' => 'Marinade', 'text' => 'Sojasoße und Honig verrühren.'],
                ['title' => '', 'text' => 'Tenders 12 Minuten im Airfryer garen.'],
            ],
        ];
    }
}
