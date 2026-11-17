<?php

namespace Tests\Feature\Api;

use App\Enums\TagType;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_lists_recipes_filtered_by_search_and_tag(): void
    {
        $dinner = Tag::create(['type' => TagType::Meal, 'key' => 'dinner', 'position' => 1]);
        $lasagne = Recipe::factory()->create(['title' => 'Lasagne']);
        $lasagne->tags()->attach($dinner);
        Recipe::factory()->create(['title' => 'Porridge']);

        $this->getJson('/api/v1/recipes')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/recipes?search=lasa')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Lasagne');

        $this->getJson("/api/v1/recipes?tag={$dinner->id}", ['Accept-Language' => 'de'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tags.0.key', 'dinner')
            ->assertJsonPath('data.0.tags.0.name', 'Abendessen');
    }

    public function test_it_shows_a_recipe_with_ingredients_and_steps(): void
    {
        $recipe = Recipe::factory()->create();
        $recipe->ingredients()->create([
            'position' => 1,
            'product_id' => Product::factory()->create(['name' => 'Mehl'])->id,
            'unit_id' => Unit::factory()->create(['code' => 'EL'])->id,
            'quantity' => 2,
        ]);
        $recipe->steps()->create(['position' => 1, 'text' => 'Mehl sieben.']);

        $this->getJson("/api/v1/recipes/{$recipe->id}")
            ->assertOk()
            ->assertJsonPath('data.ingredients.0.product.name', 'Mehl')
            ->assertJsonPath('data.ingredients.0.unit.code', 'EL')
            ->assertJsonPath('data.ingredients.0.quantity', 2)
            ->assertJsonPath('data.steps.0.text', 'Mehl sieben.');
    }

    public function test_it_rejects_an_unknown_tag_filter(): void
    {
        $this->getJson('/api/v1/recipes?tag=999')->assertUnprocessable();
    }
}
