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
     * The size of an offer is kept the way the shelf label states it, in
     * a unit of its own, instead of in the product's base unit.
     */
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->renameColumn('amount_in_base', 'amount');
            $table->string('unit', 10)->nullable();
        });

        DB::statement('UPDATE product_offers SET unit = products.base_unit FROM products WHERE products.id = product_offers.product_id AND product_offers.amount IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropColumn('unit');
            $table->renameColumn('amount', 'amount_in_base');
        });
    }
};
