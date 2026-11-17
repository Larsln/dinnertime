<?php

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
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
        Schema::create('store_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->string('name')->nullable()->unique();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->string('code')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('plural_name')->nullable();
            $table->string('kind');
            $table->decimal('factor_to_base', 12, 4)->nullable();
            $table->uuid('mealie_unit_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('plural_name')->nullable();
            $table->foreignId('store_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('base_unit')->default(BaseUnit::Gram->value);
            $table->boolean('is_pantry_staple')->default(false);
            $table->unsignedSmallInteger('shelf_life_opened_days')->nullable();
            $table->string('status')->default(ReviewStatus::Suggested->value);
            $table->timestamp('enriched_at')->nullable();
            $table->uuid('mealie_food_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('product_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_in_base', 12, 3);
            $table->string('status')->default(ReviewStatus::Suggested->value);
            $table->string('source');
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });

        Schema::create('product_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount_in_base', 12, 3);
            $table->string('status')->default(ReviewStatus::Suggested->value);
            $table->string('source');
            $table->timestamps();

            $table->unique(['product_id', 'amount_in_base']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_packages');
        Schema::dropIfExists('product_conversions');
        Schema::dropIfExists('product_aliases');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('store_sections');
    }
};
