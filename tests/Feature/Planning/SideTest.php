<?php

namespace Tests\Feature\Planning;

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class SideTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private User $anna;

    private Recipe $schnitzel;

    private Recipe $fries;

    private Recipe $rice;

    private Recipe $noodles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        CarbonImmutable::setTestNow('2026-11-16 12:00');
        $this->anna = User::factory()->create(['name' => 'Anna']);
        Sanctum::actingAs($this->anna);

        $this->schnitzel = $this->recipe('Schnitzel', [[$this->product('Schweineschnitzel'), 2]]);
        $this->schnitzel->update(['side_options' => ['potato', 'rice']]);
        $this->fries = $this->side('Fritten', 'potato');
        $this->rice = $this->side('Reis', 'rice');
        $this->noodles = $this->side('Butternudeln', 'pasta');
    }

    public function test_a_recipe_names_its_kind_and_the_kinds_it_is_served_with(): void
    {
        $this->getJson('/api/v1/side-kinds')->assertOk()->assertJsonPath('data.0', ['key' => 'potato', 'name' => 'Potatoes']);

        $this->patchJson("/api/v1/recipes/{$this->schnitzel->id}", ['side_options' => ['potato']])
            ->assertOk()
            ->assertJsonPath('data.side_options', ['potato'])
            ->assertJsonPath('data.side_kind', null);

        $this->patchJson("/api/v1/recipes/{$this->fries->id}", ['side_kind' => 'bread'])
            ->assertOk()
            ->assertJsonPath('data.side_kind', 'bread');

        $this->patchJson("/api/v1/recipes/{$this->fries->id}", ['side_kind' => 'soup'])->assertUnprocessable();
    }

    public function test_only_sides_of_the_kinds_a_dish_is_served_with_are_offered_the_latest_first(): void
    {
        $this->sidesOf($this->schnitzel)->assertJsonPath('data.*.title', ['Fritten', 'Reis']);

        $main = $this->plan($this->schnitzel, '2026-11-10')->json('data.id');
        $this->serve($main, $this->rice)->assertCreated();

        $this->sidesOf($this->schnitzel)->assertJsonPath('data.*.title', ['Reis', 'Fritten']);
        $this->sidesOf($this->rice)->assertJsonCount(0, 'data');
    }

    public function test_a_side_shares_day_meal_and_eaters_with_its_meal_and_is_shopped_for(): void
    {
        $main = $this->plan($this->schnitzel, '2026-11-17', ['eater_ids' => [$this->anna->id], 'guests' => 2])->json('data.id');

        $this->serve($main, $this->fries)
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $main)
            ->assertJsonPath('data.date', '2026-11-17')
            ->assertJsonPath('data.meal', 'dinner')
            ->assertJsonPath('data.servings', 3)
            ->assertJsonPath('data.guests', 2)
            ->assertJsonPath('data.eaters.0.user_id', $this->anna->id)
            ->assertJsonPath('data.recipe.side_kind', 'potato');

        $list = $this->postJson('/api/v1/shopping-lists', ['from' => '2026-11-16', 'to' => '2026-11-22'])->json('data');

        $this->assertEqualsCanonicalizing(
            ['Schweineschnitzel', 'Fritten Zutat'],
            array_column(array_column($list['items'], 'product'), 'name'),
        );
    }

    public function test_a_side_of_a_kind_the_dish_is_not_served_with_is_refused(): void
    {
        $main = $this->plan($this->schnitzel, '2026-11-17')->json('data.id');

        $this->serve($main, $this->noodles)->assertUnprocessable()->assertJsonValidationErrors('recipe_id');

        $side = $this->serve($main, $this->fries)->json('data.id');
        $this->serve($side, $this->rice)->assertUnprocessable();
    }

    public function test_sides_follow_their_meal(): void
    {
        $ben = User::factory()->create(['name' => 'Ben']);
        $main = $this->plan($this->schnitzel, '2026-11-17', ['servings' => 2])->json('data.id');
        $side = $this->serve($main, $this->fries)->json('data.id');

        $this->patchJson("/api/v1/meal-plan/{$main}", ['date' => '2026-11-19', 'eater_ids' => [$ben->id], 'guests' => 3, 'cooked' => true])->assertOk();

        $entry = MealPlanEntry::findOrFail($side);
        $this->assertSame('2026-11-19', $entry->date->toDateString());
        $this->assertSame(4, $entry->servings);
        $this->assertSame([$ben->id], array_column($entry->eaters, 'user_id'));
        $this->assertNotNull($entry->cooked_at);

        $this->postJson('/api/v1/meal-plan/shift', ['from' => '2026-11-01', 'days' => 1])->assertOk();
        $this->deleteJson("/api/v1/meal-plan/{$main}")->assertNoContent();
        $this->assertDatabaseMissing('meal_plan_entries', ['id' => $side]);
    }

    public function test_another_dish_keeps_only_the_sides_it_is_served_with(): void
    {
        $curry = $this->recipe('Curry', []);
        $curry->update(['side_options' => ['rice']]);
        $main = $this->plan($this->schnitzel, '2026-11-17')->json('data.id');
        $fries = $this->serve($main, $this->fries)->json('data.id');
        $rice = $this->serve($main, $this->rice)->json('data.id');

        $this->patchJson("/api/v1/meal-plan/{$main}", ['recipe_id' => $curry->id])->assertOk();

        $this->assertDatabaseMissing('meal_plan_entries', ['id' => $fries]);
        $this->assertDatabaseHas('meal_plan_entries', ['id' => $rice, 'parent_id' => $main]);
    }

    public function test_a_side_that_is_moved_becomes_a_meal_of_its_own(): void
    {
        $main = $this->plan($this->schnitzel, '2026-11-17')->json('data.id');
        $side = $this->serve($main, $this->fries)->json('data.id');

        $this->patchJson("/api/v1/meal-plan/{$side}", ['date' => '2026-11-18'])
            ->assertOk()
            ->assertJsonPath('data.parent_id', null);

        $this->assertSame('2026-11-17', MealPlanEntry::findOrFail($main)->date->toDateString());
    }

    public function test_a_verdict_on_a_side_resizes_that_side_more_than_its_meal(): void
    {
        $main = $this->plan($this->schnitzel, '2026-11-16', ['eater_ids' => [$this->anna->id]])->json('data.id');
        $side = $this->serve($main, $this->fries)->json('data.id');

        $this->putJson("/api/v1/meal-plan/{$side}/portion-feedback", ['verdict' => 'too_big'])->assertOk();

        $next = $this->plan($this->schnitzel, '2026-11-20', ['eater_ids' => [$this->anna->id]]);
        $nextSide = $this->serve($next->json('data.id'), $this->fries);

        $this->assertLessThan(1, $next->json('data.portions'));
        $this->assertLessThan($next->json('data.portions'), $nextSide->json('data.portions'));
    }

    private function side(string $title, string $kind): Recipe
    {
        $recipe = $this->recipe($title, [[$this->product("{$title} Zutat"), 1]]);
        $recipe->update(['side_kind' => $kind]);

        return $recipe;
    }

    /**
     * @param  array<string, mixed>  $who
     */
    private function plan(Recipe $recipe, string $date, array $who = []): TestResponse
    {
        return $this->postJson('/api/v1/meal-plan', ['date' => $date, 'meal' => 'dinner', 'recipe_id' => $recipe->id, ...$who])
            ->assertCreated();
    }

    private function serve(int $entryId, Recipe $side): TestResponse
    {
        return $this->postJson("/api/v1/meal-plan/{$entryId}/sides", ['recipe_id' => $side->id]);
    }

    private function sidesOf(Recipe $recipe): TestResponse
    {
        return $this->getJson("/api/v1/recipes/{$recipe->id}/sides")->assertOk();
    }
}
