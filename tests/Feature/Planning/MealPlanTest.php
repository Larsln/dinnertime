<?php

namespace Tests\Feature\Planning;

use App\Enums\MealType;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class MealPlanTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sanctum::actingAs(User::factory()->create());
        $this->app->instance(Randomizer::class, new Randomizer(new Mt19937(7)));
    }

    public function test_it_manages_plan_entries(): void
    {
        $recipe = $this->recipe('Lasagne', []);

        $id = $this->postJson('/api/v1/meal-plan', [
            'date' => '2026-11-16',
            'meal' => 'dinner',
            'recipe_id' => $recipe->id,
            'servings' => 2,
        ])->assertCreated()->assertJsonPath('data.recipe.title', 'Lasagne')->json('data.id');

        $this->patchJson("/api/v1/meal-plan/{$id}", ['servings' => 4, 'cooked' => true])
            ->assertOk()
            ->assertJsonPath('data.servings', 4)
            ->assertJsonPath('data.cooked_at', fn (?string $cookedAt) => $cookedAt !== null);

        $this->getJson('/api/v1/meal-plan?from=2026-11-16&to=2026-11-22')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/meal-plan?from=2026-11-17&to=2026-11-22')->assertJsonCount(0, 'data');

        $this->deleteJson("/api/v1/meal-plan/{$id}")->assertNoContent();
        $this->assertSame(0, MealPlanEntry::count());
    }

    public function test_it_rejects_invalid_entries(): void
    {
        $this->postJson('/api/v1/meal-plan', [
            'date' => '16.11.2026',
            'meal' => 'brunch',
            'recipe_id' => 999,
            'servings' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors(['date', 'meal', 'recipe_id', 'servings']);
    }

    public function test_the_generator_pairs_recipes_that_share_a_perishable_package(): void
    {
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 4]);
        $rice = $this->product('Reis', [500]);
        $this->recipe('Lasagne', [[$cream, 120, 'gram']]);
        $this->recipe('Suppe', [[$cream, 80, 'gram']]);
        $this->recipe('Curry', [[$rice, 150, 'gram']]);
        $this->recipe('Pfanne', [[$rice, 150, 'gram']]);
        $this->recipe('Auflauf', [[$this->product('Brokkoli', [500], ['shelf_life_opened_days' => 3]), 300, 'gram']]);
        $this->recipe('Porridge', [], 'breakfast');

        $titles = $this->generate('2026-11-16', '2026-11-17')
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->json('data.*.recipe.title');

        $this->assertEqualsCanonicalizing(['Lasagne', 'Suppe'], $titles);
    }

    public function test_the_generator_uses_up_the_pantry(): void
    {
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 4]);
        $this->recipe('Suppe', [[$cream, 80, 'gram']]);
        $this->recipe('Curry', [[$this->product('Kokosmilch', [400], ['shelf_life_opened_days' => 3]), 250, 'gram']]);
        $this->recipe('Auflauf', [[$this->product('Brokkoli', [500], ['shelf_life_opened_days' => 3]), 300, 'gram']]);
        PantryItem::create(['product_id' => $cream->id, 'amount' => 80, 'expires_on' => '2026-11-18']);

        $this->generate('2026-11-16', '2026-11-16')->assertJsonPath('data.0.recipe.title', 'Suppe');
    }

    public function test_the_generator_keeps_existing_entries_and_avoids_repeats(): void
    {
        $lasagne = $this->recipe('Lasagne', []);
        $this->recipe('Suppe', []);
        $this->recipe('Curry', []);
        MealPlanEntry::create(['date' => '2026-11-16', 'meal' => MealType::Dinner, 'recipe_id' => $lasagne->id, 'servings' => 2]);

        $titles = $this->generate('2026-11-16', '2026-11-18')->assertJsonCount(2, 'data')->json('data.*.recipe.title');

        $this->assertEqualsCanonicalizing(['Suppe', 'Curry'], $titles);
        $this->assertSame(3, MealPlanEntry::count());
    }

    public function test_the_generator_skips_meals_without_recipes(): void
    {
        $this->recipe('Lasagne', []);

        $this->postJson('/api/v1/meal-plan/generate', [
            'from' => '2026-11-16',
            'to' => '2026-11-16',
            'slots' => [
                ['date' => '2026-11-16', 'meal' => 'breakfast'],
                ['date' => '2026-11-16', 'meal' => 'dinner'],
            ],
            'servings' => 2,
        ])->assertJsonCount(1, 'data')->assertJsonPath('data.0.meal', 'dinner');
    }

    public function test_the_generator_fills_only_the_chosen_slots(): void
    {
        $this->recipe('Lasagne', []);
        $this->recipe('Suppe', []);
        $this->recipe('Porridge', [], 'breakfast');

        $entries = $this->postJson('/api/v1/meal-plan/generate', [
            'from' => '2026-11-16',
            'to' => '2026-11-22',
            'slots' => [
                ['date' => '2026-11-21', 'meal' => 'dinner'],
                ['date' => '2026-11-18', 'meal' => 'breakfast'],
                ['date' => '2026-11-18', 'meal' => 'breakfast'],
            ],
            'servings' => 3,
        ])->assertCreated()->json('data');

        $this->assertSame(
            [['2026-11-18', 'breakfast', 3], ['2026-11-21', 'dinner', 3]],
            array_map(fn (array $entry) => [$entry['date'], $entry['meal'], $entry['servings']], $entries),
        );
        $this->assertSame('Porridge', $entries[0]['recipe']['title']);
    }

    public function test_the_generator_rejects_slots_outside_the_period(): void
    {
        $this->postJson('/api/v1/meal-plan/generate', [
            'from' => '2026-11-16',
            'to' => '2026-11-22',
            'slots' => [['date' => '2026-11-23', 'meal' => 'dinner'], ['date' => '2026-11-17', 'meal' => 'brunch']],
            'servings' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors(['slots.0.date', 'slots.1.meal']);
    }

    private function generate(string $from, string $to): TestResponse
    {
        $slots = collect(CarbonPeriod::create($from, $to))
            ->map(fn ($date) => ['date' => $date->toDateString(), 'meal' => 'dinner'])
            ->all();

        return $this->postJson('/api/v1/meal-plan/generate', [
            'from' => $from,
            'to' => $to,
            'slots' => $slots,
            'servings' => 2,
        ]);
    }
}
