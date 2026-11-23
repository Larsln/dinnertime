<?php

namespace Tests\Feature\Planning;

use App\Enums\MealType;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
use App\Models\Tag;
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

    public function test_a_suggestion_pairs_recipes_that_share_a_perishable_package(): void
    {
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 4]);
        $rice = $this->product('Reis', [500]);
        $this->recipe('Lasagne', [[$cream, 120, 'gram']]);
        $this->recipe('Suppe', [[$cream, 80, 'gram']]);
        $this->recipe('Curry', [[$rice, 150, 'gram']]);
        $this->recipe('Pfanne', [[$rice, 150, 'gram']]);
        $this->recipe('Auflauf', [[$this->product('Brokkoli', [500], ['shelf_life_opened_days' => 3]), 300, 'gram']]);
        $this->recipe('Porridge', [], 'breakfast');

        $response = $this->suggest('2026-11-16', '2026-11-17')->assertOk()->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(['Lasagne', 'Suppe'], $response->json('data.*.recipe.title'));
        $this->assertSame(5, $response->json('meta.candidate_count'));
        $this->assertSame(0, MealPlanEntry::count());
    }

    public function test_a_suggestion_uses_up_the_pantry(): void
    {
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 4]);
        $this->recipe('Suppe', [[$cream, 80, 'gram']]);
        $this->recipe('Curry', [[$this->product('Kokosmilch', [400], ['shelf_life_opened_days' => 3]), 250, 'gram']]);
        $this->recipe('Auflauf', [[$this->product('Brokkoli', [500], ['shelf_life_opened_days' => 3]), 300, 'gram']]);
        PantryItem::create(['product_id' => $cream->id, 'amount' => 80, 'expires_on' => '2026-11-18']);

        $this->suggest('2026-11-16', '2026-11-16')->assertJsonPath('data.0.recipe.title', 'Suppe');
        $this->suggest('2026-11-16', '2026-11-16', ['preferences' => ['use_leftovers' => false]])->assertOk();
    }

    public function test_a_suggestion_skips_planned_slots_and_avoids_repeats(): void
    {
        $lasagne = $this->recipe('Lasagne', []);
        $this->recipe('Suppe', []);
        $this->recipe('Curry', []);
        MealPlanEntry::create(['date' => '2026-11-16', 'meal' => MealType::Dinner, 'recipe_id' => $lasagne->id, 'servings' => 2]);

        $response = $this->suggest('2026-11-16', '2026-11-18')->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(['Suppe', 'Curry'], $response->json('data.*.recipe.title'));
        $this->assertSame(['2026-11-17', '2026-11-18'], $response->json('data.*.date'));
    }

    public function test_unavoidable_repeats_are_spread_over_the_recipes(): void
    {
        $this->recipe('Lasagne', []);
        $this->recipe('Suppe', []);
        $this->recipe('Curry', []);

        $titles = $this->suggest('2026-11-16', '2026-11-21')->json('data.*.recipe.title');

        $this->assertSame(['Curry' => 2, 'Lasagne' => 2, 'Suppe' => 2], collect($titles)->countBy()->sortKeys()->all());
    }

    public function test_a_suggestion_covers_only_chosen_slots_that_have_recipes(): void
    {
        $this->recipe('Lasagne', []);
        $this->recipe('Porridge', [], 'breakfast');

        $data = $this->postJson('/api/v1/meal-plan/suggest', [
            'from' => '2026-11-16',
            'to' => '2026-11-22',
            'slots' => [
                ['date' => '2026-11-21', 'meal' => 'dinner'],
                ['date' => '2026-11-18', 'meal' => 'breakfast'],
                ['date' => '2026-11-18', 'meal' => 'breakfast'],
                ['date' => '2026-11-18', 'meal' => 'snack'],
            ],
            'servings' => 3,
        ])->assertOk()->json('data');

        $this->assertSame(
            [['2026-11-18', 'breakfast', 'Porridge', false], ['2026-11-21', 'dinner', 'Lasagne', false]],
            array_map(fn (array $row) => [$row['date'], $row['meal'], $row['recipe']['title'], $row['kept']], $data),
        );
    }

    public function test_a_suggestion_keeps_locked_recipes_and_avoids_rejected_ones(): void
    {
        $lasagne = $this->recipe('Lasagne', []);
        $soup = $this->recipe('Suppe', []);

        $data = $this->suggest('2026-11-16', '2026-11-17', [
            'keep' => [['date' => '2026-11-16', 'meal' => 'dinner', 'recipe_id' => $soup->id]],
            'avoid' => [['date' => '2026-11-17', 'meal' => 'dinner', 'recipe_id' => $lasagne->id]],
        ])->json('data');

        $this->assertSame(
            [['Suppe', true], ['Suppe', false]],
            array_map(fn (array $row) => [$row['recipe']['title'], $row['kept']], $data),
        );
    }

    public function test_weekdays_get_quick_recipes_when_asked(): void
    {
        Recipe::whereKey($this->recipe('Braten', [])->id)->update(['prep_minutes' => 30, 'cook_minutes' => 120]);
        Recipe::whereKey($this->recipe('Pfanne', [])->id)->update(['prep_minutes' => 10, 'cook_minutes' => 15]);

        $titles = $this->suggest('2026-11-20', '2026-11-21', ['preferences' => ['quick_on_weekdays' => true]])
            ->json('data.*.recipe.title');

        $this->assertSame(['Pfanne', 'Braten'], $titles);
    }

    public function test_favorites_and_good_ratings_win_when_asked(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->recipe('Suppe', []);
        $this->recipe('Curry', [])->marks()->create(['user_id' => $user->id, 'rating' => 2]);
        $this->recipe('Lasagne', [])->marks()->create(['user_id' => $user->id, 'rating' => 9, 'is_favorite' => true]);

        $this->suggest('2026-11-16', '2026-11-16', ['preferences' => ['prefer_favorites' => true]])
            ->assertJsonPath('data.0.recipe.title', 'Lasagne');
    }

    public function test_long_uncooked_recipes_come_first(): void
    {
        $recent = $this->recipe('Lasagne', []);
        $older = $this->recipe('Suppe', []);
        MealPlanEntry::create(['date' => '2026-11-12', 'meal' => MealType::Dinner, 'recipe_id' => $recent->id, 'servings' => 2]);
        MealPlanEntry::create(['date' => '2026-10-01', 'meal' => MealType::Dinner, 'recipe_id' => $older->id, 'servings' => 2]);

        $this->suggest('2026-11-16', '2026-11-16')->assertJsonPath('data.0.recipe.title', 'Suppe');
    }

    public function test_a_suggestion_can_be_limited_to_tags_and_courses(): void
    {
        $vegan = Tag::where('type', 'tag')->where('key', 'vegan')->value('id');
        $this->recipe('Lasagne', []);
        $this->recipe('Bowl', [])->tags()->attach($vegan);
        Recipe::whereKey($this->recipe('Tiramisu', [])->id)->update(['course' => 'dessert']);

        $this->suggest('2026-11-16', '2026-11-16', ['tag_ids' => [$vegan]])
            ->assertJsonPath('data.0.recipe.title', 'Bowl')
            ->assertJsonPath('meta.candidate_count', 1);

        $this->suggest('2026-11-16', '2026-11-16', ['courses' => ['dessert']])->assertJsonPath('data.0.recipe.title', 'Tiramisu');
    }

    public function test_it_rejects_invalid_suggestion_requests(): void
    {
        $this->postJson('/api/v1/meal-plan/suggest', [
            'from' => '2026-11-16',
            'to' => '2026-11-22',
            'slots' => [['date' => '2026-11-23', 'meal' => 'dinner'], ['date' => '2026-11-17', 'meal' => 'brunch']],
            'servings' => 2,
            'preferences' => ['spicy' => true],
            'courses' => ['brunch'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['slots.0.date', 'slots.1.meal', 'preferences', 'courses.0']);
    }

    public function test_accepted_suggestions_are_saved_together(): void
    {
        $lasagne = $this->recipe('Lasagne', []);
        $soup = $this->recipe('Suppe', []);

        $this->postJson('/api/v1/meal-plan/batch', ['entries' => [
            ['date' => '2026-11-16', 'meal' => 'dinner', 'recipe_id' => $lasagne->id, 'servings' => 2],
            ['date' => '2026-11-17', 'meal' => 'lunch', 'recipe_id' => $soup->id, 'servings' => 4],
        ]])->assertCreated()->assertJsonCount(2, 'data')->assertJsonPath('data.1.recipe.title', 'Suppe');

        $this->assertSame(2, MealPlanEntry::count());
        $this->postJson('/api/v1/meal-plan/batch', ['entries' => [['date' => '2026-11-16', 'meal' => 'dinner']]])->assertUnprocessable();
    }

    public function test_an_entry_can_be_moved_swapped_and_resized(): void
    {
        $entry = MealPlanEntry::create(['date' => '2026-11-16', 'meal' => MealType::Dinner, 'recipe_id' => $this->recipe('Lasagne', [])->id, 'servings' => 2]);
        $soup = $this->recipe('Suppe', [], 'lunch');

        $this->patchJson("/api/v1/meal-plan/{$entry->id}", ['date' => '2026-11-18', 'meal' => 'lunch', 'recipe_id' => $soup->id, 'servings' => 5])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-18')
            ->assertJsonPath('data.meal', 'lunch')
            ->assertJsonPath('data.recipe.title', 'Suppe')
            ->assertJsonPath('data.servings', 5);
    }

    public function test_the_plan_moves_from_a_day_on_without_cooked_meals(): void
    {
        $recipe = $this->recipe('Lasagne', []);
        $cooked = MealPlanEntry::create(['date' => '2026-11-16', 'meal' => MealType::Dinner, 'recipe_id' => $recipe->id, 'servings' => 2, 'cooked_at' => now()]);
        $before = MealPlanEntry::create(['date' => '2026-11-15', 'meal' => MealType::Dinner, 'recipe_id' => $recipe->id, 'servings' => 2]);
        $later = MealPlanEntry::create(['date' => '2026-11-22', 'meal' => MealType::Dinner, 'recipe_id' => $recipe->id, 'servings' => 2]);

        $this->postJson('/api/v1/meal-plan/shift', ['from' => '2026-11-16', 'days' => 1])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-11-23');

        $this->assertSame('2026-11-16', $cooked->refresh()->date->toDateString());
        $this->assertSame('2026-11-15', $before->refresh()->date->toDateString());
        $this->assertSame('2026-11-23', $later->refresh()->date->toDateString());
        $this->postJson('/api/v1/meal-plan/shift', ['from' => '2026-11-16', 'days' => 0])->assertJsonValidationErrors('days');
        $this->postJson('/api/v1/meal-plan/shift', ['from' => '2026-11-16', 'days' => 15])->assertJsonValidationErrors('days');
    }

    public function test_a_suggestion_tells_why_its_meals_go_together(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 4]);
        $spinach = $this->product('Spinat', [250], ['shelf_life_opened_days' => 2]);
        $lasagne = $this->recipe('Lasagne', [[$cream, 120, 'gram'], [$spinach, 200, 'gram']]);
        $lasagne->marks()->create(['user_id' => $user->id, 'rating' => 9, 'is_favorite' => true]);
        $this->recipe('Suppe', [[$cream, 80, 'gram']]);
        PantryItem::create(['product_id' => $spinach->id, 'amount' => 100]);
        MealPlanEntry::create(['date' => '2026-11-04', 'meal' => MealType::Dinner, 'recipe_id' => $lasagne->id, 'servings' => 2]);

        $proposals = collect($this->suggest('2026-11-16', '2026-11-17')->json('data'))->keyBy('recipe.title');
        $reasons = collect($proposals['Lasagne']['reasons'])->keyBy('type');

        $this->assertSame(['Sahne'], $reasons['shares']['products']);
        $this->assertSame('Suppe', $reasons['shares']['with'][0]['title']);
        $this->assertSame(['Spinat'], $reasons['pantry']['products']);
        $this->assertTrue($reasons->has('favorite'));
        $this->assertEquals(9, $reasons['rating']['value']);
        $this->assertSame(12, $reasons['last_planned']['days']);
        $this->assertContains(['type' => 'not_recent'], $proposals['Suppe']['reasons']);
    }

    public function test_the_saved_plan_tells_why_its_meals_go_together(): void
    {
        $cream = $this->product('Sahne', [200], ['shelf_life_opened_days' => 4]);
        $first = MealPlanEntry::create(['date' => '2026-11-16', 'meal' => MealType::Dinner, 'recipe_id' => $this->recipe('Lasagne', [[$cream, 120, 'gram']])->id, 'servings' => 2]);
        $second = MealPlanEntry::create(['date' => '2026-11-18', 'meal' => MealType::Dinner, 'recipe_id' => $this->recipe('Suppe', [[$cream, 80, 'gram']])->id, 'servings' => 2]);
        MealPlanEntry::create(['date' => '2026-11-25', 'meal' => MealType::Dinner, 'recipe_id' => $this->recipe('Curry', [[$cream, 80, 'gram']])->id, 'servings' => 2]);
        MealPlanEntry::create(['date' => '2026-11-20', 'meal' => MealType::Dinner, 'recipe_id' => $second->recipe_id, 'servings' => 2]);

        $this->getJson('/api/v1/meal-plan/reasons?from=2026-11-16&to=2026-11-22')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.entry_id', $first->id)
            ->assertJsonPath('data.0.reasons.0.with', [['title' => 'Suppe', 'date' => '2026-11-18'], ['title' => 'Suppe', 'date' => '2026-11-20']])
            ->assertJsonPath('data.1.entry_id', $second->id)
            ->assertJsonPath('data.1.reasons.0', ['type' => 'shares', 'products' => ['Sahne'], 'with' => [['title' => 'Lasagne', 'date' => '2026-11-16']]]);
    }

    /**
     * Ask for a dinner suggestion on every day of the period.
     *
     * @param  array<string, mixed>  $options
     * @return TestResponse<JsonResponse>
     */
    private function suggest(string $from, string $to, array $options = []): TestResponse
    {
        $slots = collect(CarbonPeriod::create($from, $to))
            ->map(fn ($date) => ['date' => $date->toDateString(), 'meal' => 'dinner'])
            ->all();

        return $this->postJson('/api/v1/meal-plan/suggest', [
            'from' => $from,
            'to' => $to,
            'slots' => $slots,
            'servings' => 2,
            ...$options,
        ]);
    }
}
