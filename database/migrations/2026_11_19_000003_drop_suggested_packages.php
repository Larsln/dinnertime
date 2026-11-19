<?php

use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Package sizes are no longer guessed by the AI: the household notes
     * them when it sees the product in a store. Guesses nobody confirmed
     * are removed.
     */
    public function up(): void
    {
        DB::table('product_packages')
            ->where('source', ValueSource::Ai->value)
            ->where('status', ReviewStatus::Suggested->value)
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
