<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\ProductEnricher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks the AI for purchase details of a product. Does nothing on instances
 * without an API key.
 */
class EnrichProduct implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(public Product $product) {}

    public function uniqueId(): string
    {
        return (string) $this->product->id;
    }

    public function handle(GeminiClient $gemini, ProductEnricher $enricher): void
    {
        if ($gemini->isConfigured()) {
            $enricher->enrich($this->product);
        }
    }
}
