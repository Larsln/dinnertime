<?php

namespace App\Console\Commands;

use App\Jobs\EnrichProduct;
use App\Models\Product;
use App\Services\Ai\GeminiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:enrich {--all : Include products that were enriched before}')]
#[Description('Queue AI suggestions for product details, package sizes and conversions')]
class EnrichProducts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GeminiClient $gemini): int
    {
        if (! $gemini->isConfigured()) {
            $this->components->error('GEMINI_API_KEY is not set.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->unless($this->option('all'), fn ($query) => $query->whereNull('enriched_at'))
            ->get();

        $products->each(fn (Product $product) => EnrichProduct::dispatch($product));
        $this->components->info("Queued {$products->count()} products.");

        return self::SUCCESS;
    }
}
