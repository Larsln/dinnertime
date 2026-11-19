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
        Schema::create('dinner_rounds', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->boolean('is_solo')->default(false);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('matched_recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dinner_round_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dinner_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('missing_count')->default(0);
            $table->unique(['dinner_round_id', 'recipe_id']);
        });

        Schema::create('dinner_round_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dinner_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('liked');
            $table->timestamps();
            $table->unique(['dinner_round_id', 'recipe_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dinner_round_votes');
        Schema::dropIfExists('dinner_round_cards');
        Schema::dropIfExists('dinner_rounds');
    }
};
