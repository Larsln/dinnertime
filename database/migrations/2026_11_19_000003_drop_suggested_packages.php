<?php

use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use App\Models\ProductPackage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Package sizes are no longer guessed by the AI: the household notes
     * them when it sees the product in a store. Guesses nobody confirmed
     * are removed one by one, so clients that keep a copy learn of it.
     */
    public function up(): void
    {
        ProductPackage::query()
            ->where('source', ValueSource::Ai)
            ->where('status', ReviewStatus::Suggested)
            ->get()
            ->each
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
