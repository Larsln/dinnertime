<?php

namespace Tests\Feature\Ai;

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
use App\Enums\ValueSource;
use App\Jobs\EnrichProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ProductEnrichmentTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        config(['services.gemini.key' => 'secret', 'services.gemini.base_url' => 'https://gemini.test']);
        Http::preventStrayRequests();
    }

    public function test_it_stores_suggestions_for_a_new_product(): void
    {
        $cream = $this->product('Sahne', attributes: ['status' => ReviewStatus::Suggested]);
        $this->recipe('Soße', [[$cream, 2, 'tablespoon'], [$cream, 100, 'milliliter'], [$cream, 50, 'gram'], [$cream, 1, 'tub']]);
        $this->fakeSuggestion([
            'icon' => 'milk',
            'store_section' => 'chilled',
            'base_unit' => 'ml',
            'is_pantry_staple' => false,
            'shelf_life_opened_days' => 4,
            'packages' => [['label' => 'Becher', 'amount' => 200], ['label' => 'Flasche', 'amount' => 500]],
            'conversions' => [
                ['unit' => 'tablespoon', 'amount' => 15],
                ['unit' => 'gram', 'amount' => 0.99],
                ['unit' => 'milliliter', 'amount' => 1],
                ['unit' => 'tub', 'amount' => null],
            ],
        ]);

        EnrichProduct::dispatchSync($cream);

        $cream->refresh();
        $this->assertSame(BaseUnit::Milliliter, $cream->base_unit);
        $this->assertSame('milk', $cream->icon);
        $this->assertSame('chilled', $cream->storeSection->key);
        $this->assertSame(4, $cream->shelf_life_opened_days);
        $this->assertNotNull($cream->enriched_at);
        $this->assertSame(ReviewStatus::Suggested, $cream->status);
        $this->assertEquals([200, 500], $cream->packages->pluck('amount_in_base')->all());
        $this->assertEquals(
            ['tablespoon' => 15, 'gram' => 0.99, 'tub' => null],
            $cream->conversions->pluck('amount_in_base', 'unit.key')->all(),
        );
        $this->assertSame(ReviewStatus::Suggested, $cream->conversions->first()->status);
        $this->assertSame(ValueSource::Ai, $cream->conversions->first()->source);

        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], '"Sahne"')
            && $request['generationConfig']['responseSchema']['properties']['conversions']['items']['properties']['unit']['enum'] === ['gram', 'milliliter', 'tablespoon', 'tub']);
    }

    public function test_it_keeps_what_a_user_confirmed(): void
    {
        $rice = $this->product('Reis', [500], ['shelf_life_opened_days' => null]);
        $this->convert($rice, 'tablespoon', 12);
        $this->recipe('Curry', [[$rice, 1, 'tablespoon'], [$rice, 2]]);
        $this->fakeSuggestion([
            'icon' => 'not-an-icon',
            'store_section' => 'other',
            'base_unit' => 'piece',
            'is_pantry_staple' => true,
            'shelf_life_opened_days' => 9,
            'packages' => [['label' => 'Beutel', 'amount' => 1000]],
            'conversions' => [['unit' => 'piece', 'amount' => 180], ['unit' => 'tablespoon', 'amount' => 99]],
        ]);

        EnrichProduct::dispatchSync($rice);

        $rice->refresh();
        $this->assertSame(BaseUnit::Gram, $rice->base_unit);
        $this->assertSame('rice', $rice->icon);
        $this->assertFalse($rice->is_pantry_staple);
        $this->assertNull($rice->shelf_life_opened_days);
        $this->assertEquals([500], $rice->packages->pluck('amount_in_base')->all());
        $this->assertEquals(['tablespoon' => 12, 'piece' => 180], $rice->conversions->pluck('amount_in_base', 'unit.key')->all());
    }

    public function test_it_does_nothing_without_an_api_key(): void
    {
        config(['services.gemini.key' => null]);
        $product = $this->product('Reis');

        EnrichProduct::dispatchSync($product);

        $this->assertNull($product->refresh()->enriched_at);
        Http::assertNothingSent();
    }

    public function test_the_command_queues_products_that_were_never_enriched(): void
    {
        Queue::fake();
        $rice = $this->product('Reis');
        $cream = $this->product('Sahne', attributes: ['enriched_at' => now()]);

        $this->artisan('products:enrich')->assertSuccessful();
        Queue::assertPushed(EnrichProduct::class, 1);
        Queue::assertPushed(EnrichProduct::class, fn (EnrichProduct $job) => $job->product->is($rice));

        $this->artisan('products:enrich --all')->assertSuccessful();
        Queue::assertPushed(EnrichProduct::class, fn (EnrichProduct $job) => $job->product->is($cream));
    }

    /**
     * @param  array<string, mixed>  $suggestion
     */
    private function fakeSuggestion(array $suggestion): void
    {
        Http::fake(['gemini.test/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($suggestion)]]]]],
        ])]);
    }
}
