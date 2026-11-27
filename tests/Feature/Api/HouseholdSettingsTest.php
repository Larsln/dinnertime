<?php

namespace Tests\Feature\Api;

use App\Models\Recipe;
use App\Models\User;
use App\Support\ProductIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_the_default_servings_can_be_changed(): void
    {
        $this->getJson('/api/v1/settings/household')->assertOk()->assertJsonPath('data.default_servings', 2);

        $this->patchJson('/api/v1/settings/household', ['default_servings' => 4])
            ->assertOk()
            ->assertJsonPath('data.default_servings', 4);

        $this->patchJson('/api/v1/settings/household', ['default_servings' => 0])->assertUnprocessable();
        $this->getJson('/api/v1/settings/household')->assertJsonPath('data.default_servings', 4);
    }

    public function test_the_note_of_stocked_needs_can_be_switched_on_alone(): void
    {
        $this->getJson('/api/v1/settings/household')->assertJsonPath('data.show_stocked_needs', false);

        $this->patchJson('/api/v1/settings/household', ['show_stocked_needs' => true])
            ->assertOk()
            ->assertJsonPath('data.show_stocked_needs', true)
            ->assertJsonPath('data.default_servings', 2);

        $this->patchJson('/api/v1/settings/household', [])->assertUnprocessable();
    }

    public function test_a_meal_is_planned_for_the_default_servings_unless_another_number_is_given(): void
    {
        $recipe = Recipe::factory()->create();
        $this->patchJson('/api/v1/settings/household', ['default_servings' => 3]);
        $entry = ['date' => '2026-11-20', 'meal' => 'dinner', 'recipe_id' => $recipe->id];

        $this->postJson('/api/v1/meal-plan', $entry)->assertCreated()->assertJsonPath('data.servings', 3);
        $this->postJson('/api/v1/meal-plan', [...$entry, 'servings' => 6])->assertCreated()->assertJsonPath('data.servings', 6);
    }

    public function test_every_icon_of_the_manifest_has_a_drawing(): void
    {
        $this->assertGreaterThan(300, count(ProductIcons::keys()));
        $this->assertSame('tomato', ProductIcons::named(' Tomate '));
        $this->assertNull(ProductIcons::named('Obst & Gemüse'));

        foreach (File::json(resource_path('icons/products/manifest.json')) as $entry) {
            $this->assertFileExists(resource_path("icons/products/{$entry['key']}.svg"));
        }
    }
}
