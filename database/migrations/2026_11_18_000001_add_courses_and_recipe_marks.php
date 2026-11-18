<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Courses the former category tags turn into. Where a recipe carries
     * several categories, the first match in this order wins. Categories
     * that named a meal make the dish a main course.
     */
    private const array COURSE_BY_CATEGORY = [
        'dessert' => 'dessert',
        'soup' => 'soup',
        'salad' => 'salad',
        'side' => 'side',
        'baking' => 'baking',
        'beverage' => 'drink',
        'snack' => 'snack',
        'breakfast' => 'main',
        'lunch' => 'main',
        'dinner' => 'main',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('course')->nullable()->index();
        });

        Schema::create('recipe_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->unique(['recipe_id', 'user_id']);
        });

        $this->replaceCategoryTagsWithCourses();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_marks');

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('course');
        });
    }

    /**
     * Derive each recipe's course from its category tags, then remove the
     * category tags, which the course replaces.
     */
    private function replaceCategoryTagsWithCourses(): void
    {
        foreach (self::COURSE_BY_CATEGORY as $category => $course) {
            $recipeIds = DB::table('recipe_tag')
                ->join('tags', 'tags.id', '=', 'recipe_tag.tag_id')
                ->where('tags.type', 'category')
                ->where('tags.key', $category)
                ->pluck('recipe_tag.recipe_id');

            DB::table('recipes')->whereIn('id', $recipeIds)->whereNull('course')->update(['course' => $course]);
        }

        DB::table('tags')->where('type', 'category')->delete();
    }
};
