<?php

namespace Tests\Unit;

use App\Models\ProductPackage;
use App\Services\Planning\PackagePicker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PackagePickerTest extends TestCase
{
    /**
     * @param  list<int>  $sizes
     * @param  array<int, int>  $expectedCounts  Number of packages keyed by size.
     */
    #[DataProvider('needs')]
    public function test_it_picks_the_combination_with_the_least_surplus(float $need, array $sizes, array $expectedCounts, float $expectedAmount): void
    {
        $packages = collect($sizes)->map(fn (int $size) => (new ProductPackage)->forceFill(['id' => $size, 'amount_in_base' => $size]));

        $selection = (new PackagePicker)->pick($need, $packages);

        $this->assertEqualsCanonicalizing($expectedCounts, $selection->counts);
        $this->assertSame($expectedCounts, array_intersect_key($selection->counts, $expectedCounts));
        $this->assertSame($expectedAmount, $selection->amount);
    }

    /**
     * @return array<string, array{float, list<int>, array<int, int>, float}>
     */
    public static function needs(): array
    {
        return [
            'two small instead of one large' => [350, [200, 500], [200 => 2], 400.0],
            'exact fit' => [400, [200], [200 => 2], 400.0],
            'one large instead of many small' => [900, [100, 1000], [1000 => 1], 1000.0],
            'mixed sizes' => [700, [200, 500], [500 => 1, 200 => 1], 700.0],
            'single size rounds up' => [250, [200], [200 => 2], 400.0],
            'no packages buys the exact amount' => [130, [], [], 130.0],
            'nothing needed' => [0, [200], [], 0.0],
        ];
    }
}
