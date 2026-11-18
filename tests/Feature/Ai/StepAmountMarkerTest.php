<?php

namespace Tests\Feature\Ai;

use App\Models\Recipe;
use App\Support\StepAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StepAmountMarkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Http::preventStrayRequests();
        config(['services.gemini.key' => 'secret', 'services.gemini.base_url' => 'https://gemini.test']);
    }

    public function test_it_marks_amounts_and_ignores_answers_that_change_the_wording(): void
    {
        $recipe = Recipe::factory()->create();
        $recipe->steps()->createMany([
            ['position' => 1, 'text' => '250 g Mehl und 1,5 EL Öl 10 Minuten kneten.'],
            ['position' => 2, 'text' => '2 Eier unterrühren.'],
            ['position' => 3, 'text' => 'Bei 180 °C backen.'],
        ]);
        $marked = Recipe::factory()->create();
        $marked->steps()->create(['position' => 1, 'text' => '{100} ml Milch erwärmen.']);
        Http::fake(['gemini.test/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['steps' => [
            '{250} g Mehl und {1,5} EL Öl 10 Minuten kneten.',
            '{2} Eier gut unterrühren.',
            'Bei 180 °C backen.',
        ]])]]]]]])]);

        $this->artisan('recipes:mark-step-amounts')->assertSuccessful();

        $this->assertSame([
            '{250} g Mehl und {1,5} EL Öl 10 Minuten kneten.',
            '2 Eier unterrühren.',
            'Bei 180 °C backen.',
        ], $recipe->steps()->orderBy('position')->pluck('text')->all());
        Http::assertSentCount(1);
    }

    public function test_it_recognises_markers(): void
    {
        $this->assertTrue(StepAmounts::has('{1/2} TL Salz'));
        $this->assertFalse(StepAmounts::has('{abc} und 2 Eier'));
        $this->assertSame('2-3 EL', StepAmounts::strip('{2}-{3} EL'));
        $this->assertFalse(StepAmounts::marks('{25} g Mehl', '250 g Mehl'));
    }
}
