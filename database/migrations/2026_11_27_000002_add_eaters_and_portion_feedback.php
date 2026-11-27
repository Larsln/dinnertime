<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Entries planned before name nobody who eats, so they keep cooking
     * one standard portion per serving.
     */
    public function up(): void
    {
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->json('eaters')->nullable();
            $table->unsignedSmallInteger('guests')->default(0);
            $table->decimal('portions', 6, 2)->nullable();
        });

        DB::table('meal_plan_entries')->update(['portions' => DB::raw('servings')]);

        Schema::create('portion_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_plan_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('verdict');
            $table->decimal('served_factor', 4, 2);
            $table->timestamps();
            $table->unique(['user_id', 'meal_plan_entry_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portion_feedback');

        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->dropColumn(['eaters', 'guests', 'portions']);
        });
    }
};
