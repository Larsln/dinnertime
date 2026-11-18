<?php

namespace Tests\Feature\Api;

use App\Enums\Course;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecipeMarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_rate_independently_and_see_the_average(): void
    {
        $recipe = Recipe::factory()->create();
        $pauline = User::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/recipes/{$recipe->id}/rating", ['rating' => 9])
            ->assertOk()
            ->assertJsonPath('data.rating', 9)
            ->assertJsonPath('data.rating_count', 1);

        Sanctum::actingAs($pauline);
        $this->getJson("/api/v1/recipes/{$recipe->id}")
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.average_rating', 9);

        $this->putJson("/api/v1/recipes/{$recipe->id}/rating", ['rating' => 6])
            ->assertJsonPath('data.rating', 6)
            ->assertJsonPath('data.average_rating', 7.5)
            ->assertJsonPath('data.rating_count', 2);

        $this->putJson("/api/v1/recipes/{$recipe->id}/rating", ['rating' => null])
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.average_rating', 9)
            ->assertJsonPath('data.rating_count', 1);
    }

    public function test_it_accepts_ratings_from_zero_to_ten_only(): void
    {
        $recipe = Recipe::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/recipes/{$recipe->id}/rating", ['rating' => 0])->assertJsonPath('data.rating', 0);
        $this->putJson("/api/v1/recipes/{$recipe->id}/rating", ['rating' => 10])->assertJsonPath('data.rating', 10);
        $this->putJson("/api/v1/recipes/{$recipe->id}/rating", ['rating' => 11])->assertUnprocessable();
        $this->putJson("/api/v1/recipes/{$recipe->id}/rating", ['rating' => 7.5])->assertUnprocessable();
        $this->putJson("/api/v1/recipes/{$recipe->id}/rating", [])->assertUnprocessable();
    }

    public function test_favorites_belong_to_the_member_and_keep_the_rating(): void
    {
        $favorite = Recipe::factory()->create(['title' => 'Lasagne']);
        Recipe::factory()->create(['title' => 'Porridge']);
        $pauline = User::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/recipes/{$favorite->id}/rating", ['rating' => 8]);
        $this->putJson("/api/v1/recipes/{$favorite->id}/favorite", ['favorite' => true])
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.rating', 8);

        $this->getJson('/api/v1/recipes?favorite=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Lasagne');

        Sanctum::actingAs($pauline);
        $this->getJson('/api/v1/recipes?favorite=1')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/recipes')->assertJsonCount(2, 'data')->assertJsonPath('data.0.is_favorite', false);
    }

    public function test_it_filters_by_course_and_lists_the_courses(): void
    {
        Recipe::factory()->create(['title' => 'Tiramisu', 'course' => Course::Dessert]);
        Recipe::factory()->create(['title' => 'Lasagne', 'course' => Course::Main]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/recipes?course=dessert', ['Accept-Language' => 'de'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course', 'dessert')
            ->assertJsonPath('data.0.course_name', 'Dessert');

        $this->getJson('/api/v1/recipes?course=brunch')->assertUnprocessable();

        $this->getJson('/api/v1/courses', ['Accept-Language' => 'de'])
            ->assertJsonCount(9, 'data')
            ->assertJsonPath('data.0', ['key' => 'starter', 'name' => 'Vorspeise'])
            ->assertJsonPath('data.3.name', 'Hauptgericht');
    }
}
