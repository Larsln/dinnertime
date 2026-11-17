<?php

namespace App\Services\Ai;

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use App\Models\Product;
use App\Models\StoreSection;
use App\Models\Unit;
use App\Services\Catalog\ConversionGaps;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lets Gemini suggest purchase details, package sizes and unit conversions
 * for a product. Suggestions never replace values a user has confirmed.
 */
class ProductEnricher
{
    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly ConversionGaps $gaps,
    ) {}

    /**
     * @throws GeminiException
     */
    public function enrich(Product $product): void
    {
        $units = $this->gaps->unitsWithoutConversion($product);
        $prompt = new ProductEnrichmentPrompt($product, $units);

        $suggestion = $this->gemini->generate(
            config('services.gemini.text_model'),
            [['text' => $prompt->text()]],
            $prompt->schema(),
        );

        DB::transaction(function () use ($product, $units, $suggestion) {
            $this->applyDetails($product, $suggestion);
            $this->applyPackages($product, $suggestion['packages'] ?? []);
            $this->applyConversions($product, $units, $suggestion['conversions'] ?? []);
        });
    }

    /**
     * Fill in the product details unless a user has confirmed the product.
     *
     * @param  array<string, mixed>  $suggestion
     */
    private function applyDetails(Product $product, array $suggestion): void
    {
        if ($product->status === ReviewStatus::Suggested) {
            $shelfLife = $suggestion['shelf_life_opened_days'] ?? null;

            $product->fill([
                'base_unit' => BaseUnit::tryFrom($suggestion['base_unit'] ?? '') ?? $product->base_unit,
                'is_pantry_staple' => (bool) ($suggestion['is_pantry_staple'] ?? false),
                'shelf_life_opened_days' => $shelfLife > 0 ? $shelfLife : null,
                'store_section_id' => $product->store_section_id
                    ?? StoreSection::where('key', $suggestion['store_section'] ?? null)->value('id'),
            ]);
        }

        $product->enriched_at = now()->toImmutable();
        $product->save();
    }

    /**
     * Suggest package sizes for products that have none yet.
     *
     * @param  list<array{label: string, amount: float|int}>  $packages
     */
    private function applyPackages(Product $product, array $packages): void
    {
        if ($product->packages()->exists()) {
            return;
        }

        foreach ($packages as $package) {
            if ($package['amount'] > 0) {
                $product->packages()->firstOrCreate(
                    ['amount_in_base' => round($package['amount'], 3)],
                    ['label' => $package['label'], 'status' => ReviewStatus::Suggested, 'source' => ValueSource::Ai],
                );
            }
        }
    }

    /**
     * Store conversions for the units that do not measure the base unit.
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<array{unit: string, amount: float|int}>  $conversions
     */
    private function applyConversions(Product $product, Collection $units, array $conversions): void
    {
        $unitsByLabel = $units->keyBy(ProductEnrichmentPrompt::unitLabel(...));

        foreach ($conversions as $conversion) {
            $unit = $unitsByLabel->get($conversion['unit']);

            if ($unit !== null && $conversion['amount'] > 0 && ! $product->base_unit->isMeasuredBy($unit)) {
                $product->conversions()->firstOrCreate(
                    ['unit_id' => $unit->id],
                    ['amount_in_base' => round($conversion['amount'], 3), 'status' => ReviewStatus::Suggested, 'source' => ValueSource::Ai],
                );
            }
        }
    }
}
