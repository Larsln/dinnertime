<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('servings')->nullable();
            $table->string('yield_text')->nullable();
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->unsignedSmallInteger('cook_minutes')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('source_platform')->nullable();
            $table->string('source_video_id')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->boolean('meal_tags_locked')->default(false);
            $table->uuid('mealie_recipe_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['source_platform', 'source_video_id']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('section')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 3)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('note')->nullable();
            $table->boolean('is_estimated')->default(false);
            $table->string('raw_text')->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title')->nullable();
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('key');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['type', 'key']);
        });

        Schema::create('recipe_tag', function (Blueprint $table) {
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['recipe_id', 'tag_id']);
        });

        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('recipe_tool', function (Blueprint $table) {
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();

            $table->primary(['recipe_id', 'tool_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_tool');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('recipe_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('recipe_steps');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
    }
};
