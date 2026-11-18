<?php

namespace Tests\Feature;

use App\Enums\Course;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string MIGRATION = 'database/migrations/2026_11_18_000001_add_courses_and_recipe_marks.php';

    public function test_category_tags_become_courses(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        $cake = Recipe::factory()->create()->id;
        $roast = Recipe::factory()->create()->id;
        $untagged = Recipe::factory()->create()->id;
        $this->tag($cake, 'category', 'dinner');
        $this->tag($cake, 'category', 'dessert');
        $this->tag($roast, 'category', 'dinner');
        $this->tag($roast, 'meal', 'dinner');

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $this->assertSame(Course::Dessert, Recipe::find($cake)->course);
        $this->assertSame(Course::Main, Recipe::find($roast)->course);
        $this->assertNull(Recipe::find($untagged)->course);
        $this->assertSame(0, DB::table('tags')->where('type', 'category')->count());
        $this->assertSame(['meal'], Recipe::find($roast)->tags()->pluck('type')->map->value->all());
    }

    private function tag(int $recipeId, string $type, string $key): void
    {
        $tagId = DB::table('tags')->where(['type' => $type, 'key' => $key])->value('id')
            ?? DB::table('tags')->insertGetId(['type' => $type, 'key' => $key, 'position' => 1]);

        DB::table('recipe_tag')->insert(['recipe_id' => $recipeId, 'tag_id' => $tagId]);
    }
}
