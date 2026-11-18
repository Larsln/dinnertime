<?php

namespace Tests\Feature\Api;

use App\Enums\TagType;
use App\Jobs\EnrichProduct;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Queue::fake();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_creates_a_recipe_with_all_parts(): void
    {
        $flour = $this->product('Mehl', attributes: ['enriched_at' => now()]);
        $onion = $this->product('Zwiebel', attributes: ['enriched_at' => now()]);
        $dinner = Tag::where('type', TagType::Meal)->where('key', 'dinner')->value('id');

        $response = $this->postJson('/api/v1/recipes', [
            'title' => 'Zwiebelkuchen',
            'servings' => 4,
            'tag_ids' => [$dinner],
            'tools' => ['Backofen', 'Springform'],
            'ingredients' => [
                ['section' => 'Teig', 'product_id' => $flour->id, 'quantity' => 250, 'unit_id' => Unit::where('key', 'gram')->value('id')],
                ['product_id' => $onion->id, 'quantity' => 3, 'note' => 'in Ringen'],
                ['raw_text' => 'etwas Kümmel'],
            ],
            'steps' => [
                ['title' => 'Teig', 'text' => 'Mehl mit Wasser verkneten.'],
                ['text' => 'Zwiebeln andünsten.'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'zwiebelkuchen')
            ->assertJsonPath('data.meal_tags_locked', true)
            ->assertJsonPath('data.ingredients.0.section', 'Teig')
            ->assertJsonPath('data.ingredients.1.product.name', 'Zwiebel')
            ->assertJsonPath('data.ingredients.2.raw_text', 'etwas Kümmel')
            ->assertJsonPath('data.steps.1.position', 2)
            ->assertJsonPath('data.tools.0.name', 'Backofen')
            ->assertJsonPath('data.tags.0.key', 'dinner');

        Queue::assertPushed(EnrichProduct::class, fn (EnrichProduct $job) => $job->product->is($onion));
        Queue::assertNotPushed(EnrichProduct::class, fn (EnrichProduct $job) => $job->product->is($flour));
    }

    public function test_it_numbers_slugs_of_recipes_with_the_same_title(): void
    {
        $this->postJson('/api/v1/recipes', ['title' => 'Lasagne'])->assertJsonPath('data.slug', 'lasagne');
        $this->postJson('/api/v1/recipes', ['title' => 'Lasagne'])->assertJsonPath('data.slug', 'lasagne-2');
    }

    public function test_an_update_replaces_only_the_lists_it_contains(): void
    {
        $recipe = $this->recipe('Lasagne', [[$this->product('Mehl'), 100, 'gram']]);
        $recipe->steps()->create(['position' => 1, 'text' => 'Alt']);

        $this->patchJson("/api/v1/recipes/{$recipe->id}", [
            'title' => 'Lasagne al forno',
            'steps' => [['text' => 'Neu 1'], ['text' => 'Neu 2']],
        ])->assertOk()
            ->assertJsonPath('data.title', 'Lasagne al forno')
            ->assertJsonPath('data.slug', $recipe->slug)
            ->assertJsonPath('data.meal_tags_locked', false)
            ->assertJsonCount(1, 'data.ingredients')
            ->assertJsonCount(2, 'data.steps');
    }

    public function test_it_rejects_incomplete_lines(): void
    {
        $this->postJson('/api/v1/recipes', [
            'title' => 'Kaputt',
            'ingredients' => [['quantity' => 2]],
            'steps' => [['title' => 'Ohne Text']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ingredients.0.raw_text', 'steps.0.text']);
    }

    public function test_it_replaces_the_cover_and_removes_it_with_the_recipe(): void
    {
        $recipe = Recipe::factory()->create();

        $first = $this->postJson("/api/v1/recipes/{$recipe->id}/cover", ['image' => $this->image('a.png')])
            ->assertOk()
            ->assertJsonPath('data.cover_url', fn (?string $url) => str_contains((string) $url, '/storage/covers/'));
        $firstPath = $recipe->refresh()->cover_image_path;

        $this->postJson("/api/v1/recipes/{$recipe->id}/cover", ['image' => $this->image('b.png')])->assertOk();
        $secondPath = $recipe->refresh()->cover_image_path;

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $this->deleteJson("/api/v1/recipes/{$recipe->id}")->assertNoContent();
        Storage::disk('public')->assertMissing($secondPath);
        $this->assertNotNull($first);
    }

    /**
     * Smallest valid PNG, so the test does not depend on an image extension.
     */
    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ));
    }
}
