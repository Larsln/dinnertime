<?php

namespace Tests\Unit;

use App\Enums\PortionVerdict;
use App\Services\Portions\PortionEstimator;
use PHPUnit\Framework\TestCase;

class PortionEstimatorTest extends TestCase
{
    public function test_repeating_a_verdict_keeps_adjusting(): void
    {
        $estimator = new PortionEstimator;

        $this->assertSame(1.0, $estimator->forRecipe([]));
        $this->assertSame(1.15, $estimator->forRecipe([[PortionVerdict::TooSmall, 1.0]]));
        $this->assertSame(1.32, $estimator->forRecipe([[PortionVerdict::TooSmall, 1.0], [PortionVerdict::TooSmall, 1.15]]));
        $this->assertSame(0.87, $estimator->forRecipe([[PortionVerdict::TooBig, 1.0]]));
    }

    public function test_opposite_verdicts_settle_in_between_and_narrow_down(): void
    {
        $estimator = new PortionEstimator;
        $history = [[PortionVerdict::TooSmall, 1.0], [PortionVerdict::TooBig, 1.15]];

        $this->assertSame(1.07, $estimator->forRecipe($history));
        $this->assertSame(1.11, $estimator->forRecipe([...$history, [PortionVerdict::TooSmall, 1.07]]));
        $this->assertSame(1.11, $estimator->forRecipe([...$history, [PortionVerdict::TooSmall, 1.07], [PortionVerdict::Right, 1.11]]));
    }

    public function test_a_verdict_on_an_outdated_portion_does_not_undo_what_was_learned(): void
    {
        $estimator = new PortionEstimator;

        $this->assertSame(1.32, $estimator->forRecipe([
            [PortionVerdict::TooSmall, 1.0],
            [PortionVerdict::TooSmall, 1.15],
            [PortionVerdict::TooSmall, 1.0],
        ]));
    }

    public function test_a_changed_appetite_replaces_older_bounds(): void
    {
        $estimator = new PortionEstimator;

        $this->assertSame(0.87, $estimator->forRecipe([
            [PortionVerdict::TooSmall, 1.0],
            [PortionVerdict::TooSmall, 1.15],
            [PortionVerdict::TooBig, 1.0],
        ]));
    }

    public function test_it_stays_within_half_and_double_a_portion(): void
    {
        $estimator = new PortionEstimator;

        $this->assertSame(2.0, $estimator->forRecipe([[PortionVerdict::TooSmall, 1.9]]));
        $this->assertSame(0.5, $estimator->forRecipe([[PortionVerdict::TooBig, 0.52]]));
    }

    public function test_other_recipes_follow_cautiously(): void
    {
        $estimator = new PortionEstimator;

        $this->assertSame(1.0, $estimator->inGeneral([]));
        $this->assertSame(1.05, $estimator->inGeneral([1.15]));
        $this->assertSame(1.11, $estimator->inGeneral([1.15, 1.15, 1.15, 1.15, 1.15, 1.15]));
    }
}
