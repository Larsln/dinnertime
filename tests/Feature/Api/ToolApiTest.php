<?php

namespace Tests\Feature\Api;

use App\Models\Recipe;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ToolApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_lists_and_searches_the_catalog(): void
    {
        $oven = Tool::create(['name' => 'Backofen']);
        $airfryer = Tool::create(['name' => 'Airfryer', 'is_owned' => false]);
        Recipe::factory()->create()->tools()->attach($oven);

        $this->getJson('/api/v1/tools')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', ['id' => $airfryer->id, 'name' => 'Airfryer', 'is_owned' => false, 'recipe_count' => 0])
            ->assertJsonPath('data.1.recipe_count', 1);

        $this->getJson('/api/v1/tools?search=ofen')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Backofen');
    }

    public function test_it_creates_renames_and_marks_tools_as_owned(): void
    {
        $id = $this->postJson('/api/v1/tools', ['name' => 'Küchenbrenner'])
            ->assertCreated()
            ->assertJsonPath('data.is_owned', true)
            ->json('data.id');

        $this->patchJson("/api/v1/tools/{$id}", ['is_owned' => false])
            ->assertOk()
            ->assertJsonPath('data.is_owned', false)
            ->assertJsonPath('data.name', 'Küchenbrenner');

        $this->patchJson("/api/v1/tools/{$id}", ['name' => 'Flambierbrenner'])->assertJsonPath('data.name', 'Flambierbrenner');
        $this->postJson('/api/v1/tools', ['name' => 'Flambierbrenner'])->assertUnprocessable();
        $this->postJson('/api/v1/tools', [])->assertUnprocessable();
    }

    public function test_it_merges_a_duplicate_into_another_tool(): void
    {
        $pan = Tool::create(['name' => 'Pfanne']);
        $duplicate = Tool::create(['name' => 'Bratpfanne']);
        $both = Recipe::factory()->create();
        $both->tools()->attach([$pan->id, $duplicate->id]);
        $onlyDuplicate = Recipe::factory()->create();
        $onlyDuplicate->tools()->attach($duplicate);

        $this->postJson("/api/v1/tools/{$duplicate->id}/merge", ['into' => $pan->id])
            ->assertOk()
            ->assertJsonPath('data.name', 'Pfanne')
            ->assertJsonPath('data.recipe_count', 2);

        $this->assertNull(Tool::find($duplicate->id));
        $this->assertSame(['Pfanne'], $both->tools()->pluck('name')->all());
        $this->assertSame(['Pfanne'], $onlyDuplicate->tools()->pluck('name')->all());
        $this->postJson("/api/v1/tools/{$pan->id}/merge", ['into' => $pan->id])->assertUnprocessable();
    }

    public function test_it_only_deletes_unused_tools(): void
    {
        $used = Tool::create(['name' => 'Topf']);
        $unused = Tool::create(['name' => 'Wok']);
        Recipe::factory()->create()->tools()->attach($used);

        $this->deleteJson("/api/v1/tools/{$used->id}")->assertConflict();
        $this->deleteJson("/api/v1/tools/{$unused->id}")->assertNoContent();
    }
}
