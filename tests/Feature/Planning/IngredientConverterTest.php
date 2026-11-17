<?php

namespace Tests\Feature\Planning;

use App\Enums\BaseUnit;
use App\Enums\ReviewStatus;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Planning\IngredientConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class IngredientConverterTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_it_converts_mass_units_by_their_factor(): void
    {
        $amount = $this->convertLine($this->recipe('A', [[$this->product('Mehl'), 1.5, 'kilogram']]));

        $this->assertSame(1500.0, $amount->amount);
        $this->assertFalse($amount->approximate);
    }

    public function test_it_uses_the_product_conversion_for_spoons_and_pieces(): void
    {
        $flour = $this->product('Mehl');
        $this->convert($flour, 'tablespoon', 10);
        $onion = $this->product('Zwiebel');
        $this->convert($onion, 'piece', 100, ReviewStatus::Suggested);

        $spoons = $this->convertLine($this->recipe('A', [[$flour, 3, 'tablespoon']]));
        $pieces = $this->convertLine($this->recipe('B', [[$onion, 2]]));

        $this->assertSame(30.0, $spoons->amount);
        $this->assertFalse($spoons->approximate);
        $this->assertSame(200.0, $pieces->amount);
        $this->assertTrue($pieces->approximate);
    }

    public function test_it_counts_pieces_of_products_sold_by_the_piece(): void
    {
        $lemon = $this->product('Zitrone', attributes: ['base_unit' => BaseUnit::Piece]);

        $this->assertSame(2.0, $this->convertLine($this->recipe('A', [[$lemon, 2]]))->amount);
        $this->assertSame(3.0, $this->convertLine($this->recipe('B', [[$lemon, 3, 'piece']]))->amount);
    }

    public function test_it_scales_a_volume_conversion_to_larger_units(): void
    {
        $cream = $this->product('Sahne');
        $this->convert($cream, 'milliliter', 1.01);

        $amount = $this->convertLine($this->recipe('A', [[$cream, 0.2, 'liter']]));

        $this->assertEqualsWithDelta(202.0, $amount->amount, 0.001);
        $this->assertFalse($amount->approximate);
    }

    public function test_it_assumes_water_density_without_a_conversion(): void
    {
        $amount = $this->convertLine($this->recipe('A', [[$this->product('Sahne'), 200, 'milliliter']]));

        $this->assertSame(200.0, $amount->amount);
        $this->assertTrue($amount->approximate);
    }

    public function test_it_gives_up_without_quantity_or_conversion(): void
    {
        $salt = $this->product('Salz');

        $this->assertNull($this->convertLine($this->recipe('A', [[$salt, null]])));
        $this->assertNull($this->convertLine($this->recipe('B', [[$salt, 1, 'pinch']])));
        $this->assertNull($this->convertLine($this->recipe('C', [[$salt, 2]])));
    }

    private function convertLine(Recipe $recipe): mixed
    {
        /** @var RecipeIngredient $ingredient */
        $ingredient = $recipe->ingredients()->with('unit', 'product.conversions.unit')->sole();

        return (new IngredientConverter)->convert($ingredient);
    }
}
