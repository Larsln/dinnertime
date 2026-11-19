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
     * A package size is no longer a thing of its own: it is the size a
     * store sells the product in, or a size sold everywhere when the offer
     * names no store. Stores the household usually goes to are marked as
     * preferred, so their sizes are bought first.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_preferred')->default(false);
        });

        Schema::table('product_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->change();
        });

        DB::statement(<<<'SQL'
            INSERT INTO product_offers (product_id, amount, unit, created_at, updated_at)
            SELECT product_packages.product_id, product_packages.amount_in_base, products.base_unit, now(), now()
            FROM product_packages JOIN products ON products.id = product_packages.product_id
            SQL);

        Schema::dropIfExists('product_packages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('product_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->decimal('amount_in_base', 12, 3);
            $table->string('status');
            $table->string('source');
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER product_packages_sync_touch AFTER INSERT OR UPDATE OR DELETE ON product_packages
            FOR EACH ROW EXECUTE FUNCTION sync_touch_owner('products', 'product_id');
            SQL);

        DB::table('product_offers')->whereNull('store_id')->delete();

        Schema::table('product_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable(false)->change();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('is_preferred');
        });
    }
};
