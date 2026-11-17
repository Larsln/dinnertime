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
        Schema::create('meal_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('meal');
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('servings');
            $table->timestamp('cooked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pantry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 3);
            $table->date('opened_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamps();
        });

        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->id();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamp('leftovers_stocked_at')->nullable();
            $table->timestamps();

            $table->unique(['starts_on', 'ends_on']);
        });

        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->decimal('needed_amount', 12, 3)->nullable();
            $table->decimal('purchase_amount', 12, 3)->nullable();
            $table->json('packages')->nullable();
            $table->decimal('leftover_amount', 12, 3)->default(0);
            $table->decimal('wasted_amount', 12, 3)->default(0);
            $table->json('uses')->nullable();
            $table->boolean('is_approximate')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
        Schema::dropIfExists('shopping_lists');
        Schema::dropIfExists('pantry_items');
        Schema::dropIfExists('meal_plan_entries');
    }
};
