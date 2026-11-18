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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedSmallInteger('status');
            $table->longText('body');
            $table->timestamp('created_at')->index();
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('cover_thumb_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('cover_thumb_path');
        });

        Schema::dropIfExists('idempotency_keys');
    }
};
