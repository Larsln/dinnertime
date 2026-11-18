<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipe_recipe_import', function (Blueprint $table) {
            $table->foreignId('recipe_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['recipe_import_id', 'recipe_id']);
        });

        DB::statement('INSERT INTO recipe_recipe_import (recipe_import_id, recipe_id) SELECT id, recipe_id FROM recipe_imports WHERE recipe_id IS NOT NULL');

        Schema::table('recipe_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_id');
            $table->unsignedTinyInteger('dish_count')->nullable();
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropUnique(['source_platform', 'source_video_id']);
            $table->index(['source_platform', 'source_video_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['source_platform', 'source_video_id']);
            $table->unique(['source_platform', 'source_video_id']);
        });

        Schema::table('recipe_imports', function (Blueprint $table) {
            $table->dropColumn('dish_count');
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::statement('UPDATE recipe_imports SET recipe_id = (SELECT recipe_id FROM recipe_recipe_import WHERE recipe_import_id = recipe_imports.id ORDER BY position LIMIT 1)');

        Schema::dropIfExists('recipe_recipe_import');
    }
};
