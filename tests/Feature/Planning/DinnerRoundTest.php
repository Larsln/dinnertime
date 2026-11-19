<?php

namespace Tests\Feature\Planning;

use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class DinnerRoundTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private User $first;

    private User $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        CarbonImmutable::setTestNow('2026-11-19 18:00:00');
        $this->first = User::factory()->create(['name' => 'Lars']);
        $this->second = User::factory()->create(['name' => 'Mia']);
    }

    public function test_the_first_recipe_both_like_is_the_match_and_gets_planned(): void
    {
        $rice = $this->product('Reis');
        $curry = $this->recipe('Curry', [[$rice, 200, 'gram']]);
        $bowl = $this->recipe('Bowl', [[$rice, 100, 'gram']]);
        $this->recipe('Müsli', [[$rice, 50, 'gram']], 'breakfast');

        Sanctum::actingAs($this->first);
        $round = $this->postJson('/api/v1/dinner-rounds')->assertCreated()->assertJsonCount(2, 'data.cards')->json('data');
        $votes = "/api/v1/dinner-rounds/{$round['id']}/votes";
        $this->postJson($votes, ['recipe_id' => $curry->id, 'liked' => false])->assertOk()->assertJsonPath('data.status', 'voting');
        $this->postJson($votes, ['recipe_id' => $bowl->id, 'liked' => true])->assertOk()->assertJsonPath('data.match', null);

        Sanctum::actingAs($this->second);
        $this->postJson('/api/v1/dinner-rounds')->assertOk()->assertJsonPath('data.id', $round['id']);
        $this->getJson('/api/v1/dinner-rounds/current')
            ->assertJsonPath('data.members.0.name', 'Lars')
            ->assertJsonPath('data.cards.0.my_vote', null)
            ->assertJsonPath('data.cards.0.liked_by_others', false);
        $this->postJson($votes, ['recipe_id' => $bowl->id, 'liked' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'matched')
            ->assertJsonPath('data.match.title', 'Bowl');
        $this->postJson($votes, ['recipe_id' => $curry->id, 'liked' => true])->assertConflict();

        $this->postJson("/api/v1/dinner-rounds/{$round['id']}/plan")
            ->assertCreated()
            ->assertJsonPath('data.meal', 'dinner')
            ->assertJsonPath('data.date', '2026-11-19');
        $this->assertSame($bowl->id, MealPlanEntry::sole()->recipe_id);
        $this->getJson('/api/v1/dinner-rounds/current')->assertJsonPath('data', null);
    }

    public function test_without_a_match_the_others_likes_get_a_second_chance(): void
    {
        $rice = $this->product('Reis');
        $curry = $this->recipe('Curry', [[$rice, 200, 'gram']]);
        $bowl = $this->recipe('Bowl', [[$rice, 100, 'gram']]);

        Sanctum::actingAs($this->first);
        $round = $this->postJson('/api/v1/dinner-rounds')->json('data');
        $votes = "/api/v1/dinner-rounds/{$round['id']}/votes";
        $this->postJson($votes, ['recipe_id' => $curry->id, 'liked' => true]);
        $this->postJson($votes, ['recipe_id' => $bowl->id, 'liked' => false]);
        $this->postJson("/api/v1/dinner-rounds/{$round['id']}/plan")->assertConflict();

        Sanctum::actingAs($this->second);
        $this->postJson($votes, ['recipe_id' => $curry->id, 'liked' => false]);
        $undecided = $this->postJson($votes, ['recipe_id' => $bowl->id, 'liked' => false])
            ->assertJsonPath('data.status', 'undecided')
            ->json('data.cards');
        $this->assertSame([true], array_column(array_filter($undecided, fn (array $card) => $card['recipe']['id'] === $curry->id), 'liked_by_others'));

        $this->postJson($votes, ['recipe_id' => $curry->id, 'liked' => true])->assertJsonPath('data.status', 'matched');
        $this->deleteJson("/api/v1/dinner-rounds/{$round['id']}")->assertNoContent();
        $this->assertSame(0, MealPlanEntry::count());
    }

    public function test_it_offers_what_needs_the_least_shopping_and_decides_alone(): void
    {
        $rice = $this->product('Reis');
        $tofu = $this->product('Tofu');
        $salt = $this->product('Salz', attributes: ['is_pantry_staple' => true]);
        $curry = $this->recipe('Curry', [[$rice, 200, 'gram'], [$tofu, 200, 'gram']]);
        $bowl = $this->recipe('Bowl', [[$rice, 100, 'gram'], [$salt, 1, 'gram']]);
        $recent = $this->recipe('Pfanne', [[$rice, 100, 'gram']]);
        PantryItem::create(['product_id' => $rice->id, 'amount' => 500]);
        MealPlanEntry::create(['date' => '2026-11-17', 'meal' => 'dinner', 'recipe_id' => $recent->id, 'servings' => 2]);

        Sanctum::actingAs($this->first);
        $round = $this->postJson('/api/v1/dinner-rounds', ['is_solo' => true])
            ->assertCreated()
            ->assertJsonPath('data.cards.0.recipe.id', $bowl->id)
            ->assertJsonPath('data.cards.0.missing_count', 0)
            ->assertJsonPath('data.cards.1.recipe.id', $curry->id)
            ->assertJsonPath('data.cards.1.missing_count', 1)
            ->assertJsonCount(2, 'data.cards')
            ->json('data');

        $this->postJson("/api/v1/dinner-rounds/{$round['id']}/votes", ['recipe_id' => $bowl->id, 'liked' => true])
            ->assertJsonPath('data.status', 'matched');
        $this->deleteJson("/api/v1/dinner-rounds/{$round['id']}")->assertNoContent();

        $this->postJson('/api/v1/dinner-rounds', ['only_available' => true])->assertCreated()->assertJsonCount(1, 'data.cards');
    }
}
