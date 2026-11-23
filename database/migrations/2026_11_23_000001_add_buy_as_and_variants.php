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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('buy_as_id')->nullable()->constrained('products')->nullOnDelete();
        });

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->json('variants')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('variants');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buy_as_id');
        });
    }
};
