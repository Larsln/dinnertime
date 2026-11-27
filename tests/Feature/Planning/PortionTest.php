<?php

namespace Tests\Feature\Planning;

use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PortionTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private User $anna;

    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        CarbonImmutable::setTestNow('2026-11-16 12:00');
        $this->anna = User::factory()->create(['name' => 'Anna']);
        $this->ben = User::factory()->create(['name' => 'Ben']);
        Sanctum::actingAs($this->anna);
    }

    public function test_servings_that_seat_the_household_name_its_members_and_fill_up_with_guests(): void
    {
        $recipe = $this->recipe('Lasagne', []);

        $this->planFor($recipe, '2026-11-16', ['servings' => 3])
            ->assertJsonPath('data.servings', 3)
            ->assertJsonPath('data.portions', 3)
            ->assertJsonPath('data.guests', 1)
            ->assertJsonPath('data.eaters', [
                ['user_id' => $this->anna->id, 'factor' => 1, 'name' => 'Anna'],
                ['user_id' => $this->ben->id, 'factor' => 1, 'name' => 'Ben'],
            ]);

        $this->planFor($recipe, '2026-11-17', ['servings' => 1])
            ->assertJsonPath('data.eaters', null)
            ->assertJsonPath('data.portions', 1);
    }

    public function test_who_eats_sets_the_servings(): void
    {
        $recipe = $this->recipe('Lasagne', []);

        $id = $this->planFor($recipe, '2026-11-16', ['eater_ids' => [$this->ben->id], 'guests' => 2])
            ->assertJsonPath('data.servings', 3)
            ->assertJsonPath('data.guests', 2)
            ->assertJsonCount(1, 'data.eaters')
            ->json('data.id');

        $this->patchJson("/api/v1/meal-plan/{$id}", ['eater_ids' => [], 'guests' => 4])
            ->assertJsonPath('data.servings', 4)
            ->assertJsonPath('data.eaters', []);
        $this->patchJson("/api/v1/meal-plan/{$id}", ['eater_ids' => [], 'guests' => 0])->assertUnprocessable();
        $this->patchJson("/api/v1/meal-plan/{$id}", ['eater_ids' => [999999]])->assertUnprocessable();
    }

    public function test_a_verdict_resizes_the_meals_ahead_and_their_shopping(): void
    {
        $rice = $this->product('Reis', [100]);
        $curry = $this->recipe('Curry', [[$rice, 200, 'gram']]);
        $eaten = $this->planFor($curry, '2026-11-16', ['servings' => 2])->json('data.id');
        $ahead = $this->planFor($curry, '2026-11-18', ['servings' => 2])->json('data.id');
        $other = $this->planFor($this->recipe('Suppe', []), '2026-11-19', ['servings' => 2])->json('data.id');
        $this->postJson('/api/v1/shopping-lists', ['from' => '2026-11-18', 'to' => '2026-11-19'])
            ->assertJsonPath('data.items.0.needed_amount', 200);

        $this->putJson("/api/v1/meal-plan/{$eaten}/portion-feedback", ['verdict' => 'too_small'])
            ->assertOk()
            ->assertJsonPath('data.portions', 2)
            ->assertJsonPath('data.portion_feedback', [['user_id' => $this->anna->id, 'verdict' => 'too_small']]);

        $entries = collect($this->getJson('/api/v1/meal-plan?from=2026-11-16&to=2026-11-22')->json('data'))->keyBy('id');

        $this->assertEquals(2.15, $entries[$ahead]['portions']);
        $this->assertEquals(1.15, $entries[$ahead]['eaters'][0]['factor']);
        $this->assertEquals(1, $entries[$ahead]['eaters'][1]['factor']);
        $this->assertEquals(2.05, $entries[$other]['portions']);
        $this->assertEquals(215, $this->getJson('/api/v1/shopping-lists/current')->json('data.items.0.needed_amount'));
    }

    public function test_the_next_verdict_builds_on_the_portion_that_was_served(): void
    {
        $curry = $this->recipe('Curry', []);
        $first = $this->planFor($curry, '2026-11-16', ['servings' => 2])->json('data.id');
        $second = $this->planFor($curry, '2026-11-17', ['servings' => 2])->json('data.id');
        $third = $this->planFor($curry, '2026-11-18', ['servings' => 2])->json('data.id');

        $this->putJson("/api/v1/meal-plan/{$first}/portion-feedback", ['verdict' => 'too_small'])->assertOk();
        $this->putJson("/api/v1/meal-plan/{$second}/portion-feedback", ['verdict' => 'too_big'])
            ->assertJsonPath('data.eaters.0.factor', 1.15);
        $this->assertEquals(1.07, $this->factorOfAnna($third));

        $this->putJson("/api/v1/meal-plan/{$second}/portion-feedback", ['verdict' => 'too_small'])->assertOk();
        $this->assertEquals(1.32, $this->factorOfAnna($third));

        $this->putJson("/api/v1/meal-plan/{$second}/portion-feedback", ['verdict' => null])
            ->assertJsonPath('data.portion_feedback', []);
        $this->assertEquals(1.15, $this->factorOfAnna($third));
    }

    public function test_verdicts_are_personal(): void
    {
        $curry = $this->recipe('Curry', []);
        $eaten = $this->planFor($curry, '2026-11-16', ['servings' => 2])->json('data.id');
        $ahead = $this->planFor($curry, '2026-11-18', ['servings' => 2])->json('data.id');

        $this->putJson("/api/v1/meal-plan/{$eaten}/portion-feedback", ['verdict' => 'too_small'])->assertOk();
        Sanctum::actingAs($this->ben);
        $this->putJson("/api/v1/meal-plan/{$eaten}/portion-feedback", ['verdict' => 'too_big'])
            ->assertJsonCount(2, 'data.portion_feedback');

        $eaters = $this->getJson('/api/v1/meal-plan?from=2026-11-18&to=2026-11-18')->json('data.0.eaters');

        $this->assertEquals([1.15, 0.87], array_column($eaters, 'factor'));
        $this->getJson('/api/v1/settings/household')->assertJsonPath('data.members', [
            ['id' => $this->anna->id, 'name' => 'Anna'],
            ['id' => $this->ben->id, 'name' => 'Ben'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $who
     */
    private function planFor(Recipe $recipe, string $date, array $who): TestResponse
    {
        return $this->postJson('/api/v1/meal-plan', ['date' => $date, 'meal' => 'dinner', 'recipe_id' => $recipe->id, ...$who])
            ->assertCreated();
    }

    private function factorOfAnna(int $entryId): float
    {
        $entry = collect($this->getJson('/api/v1/meal-plan?from=2026-11-16&to=2026-11-22')->json('data'))->firstWhere('id', $entryId);

        return (float) $entry['eaters'][0]['factor'];
    }
}
