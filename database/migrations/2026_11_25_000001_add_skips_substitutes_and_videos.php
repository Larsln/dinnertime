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
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->timestamp('skipped_at')->nullable();
            $table->foreignId('substitute_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('substitute_label')->nullable();
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('video_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('video_path');
        });

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('substitute_product_id');
            $table->dropColumn(['skipped_at', 'substitute_label']);
        });
    }
};
