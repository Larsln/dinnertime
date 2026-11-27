<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recipes and entries that exist name no sides, so they stay what
     * they were.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('side_kind')->nullable()->index();
            $table->json('side_options')->nullable();
        });

        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('meal_plan_entries')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['side_kind', 'side_options']);
        });
    }
};
