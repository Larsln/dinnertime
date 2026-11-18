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
     * An offer is part of its product for clients that keep a copy, so
     * every change to one gives the product a new change number.
     */
    public function up(): void
    {
        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('store', 100)->index();
            $table->decimal('amount_in_base', 12, 3)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER product_offers_sync_touch AFTER INSERT OR UPDATE OR DELETE ON product_offers
            FOR EACH ROW EXECUTE FUNCTION sync_touch_owner('products', 'product_id');
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_offers');
    }
};
