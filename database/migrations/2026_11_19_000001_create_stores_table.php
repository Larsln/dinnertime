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
     * Stores become records of their own that offers point to, so a store
     * can be renamed or paused in one place. Clients keep a copy of them
     * like of the tools.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sync_version')->default(0)->index();
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stores_sync_stamp BEFORE INSERT OR UPDATE ON stores
            FOR EACH ROW EXECUTE FUNCTION sync_stamp();
            CREATE TRIGGER stores_sync_forget AFTER DELETE ON stores
            FOR EACH ROW EXECUTE FUNCTION sync_forget();
            SQL);

        Schema::table('product_offers', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::statement('INSERT INTO stores (name, created_at, updated_at) SELECT DISTINCT store, now(), now() FROM product_offers');
        DB::statement('UPDATE product_offers SET store_id = stores.id FROM stores WHERE stores.name = product_offers.store');

        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropColumn('store');
            $table->unsignedBigInteger('store_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->string('store', 100)->default('')->index();
        });

        DB::statement('UPDATE product_offers SET store = stores.name FROM stores WHERE stores.id = product_offers.store_id');

        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::dropIfExists('stores');
    }
};
