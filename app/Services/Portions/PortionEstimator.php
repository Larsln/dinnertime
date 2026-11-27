<?php

namespace App\Services\Portions;

use App\Enums\PortionVerdict;

/**
 * Estimates how much of a standard portion suits someone, from what they
 * said about the portions they were served.
 *
 * Every verdict bounds the right size: a portion that was too small lies
 * below it, one that was too big above it. With a bound on one side only
 * the estimate moves a step past it, so repeating a verdict keeps
 * adjusting. With bounds on both sides it settles between them, and every
 * further verdict narrows the gap. A verdict that contradicts an older
 * bound replaces it, so the estimate follows an appetite that changes.
 */
class PortionEstimator
{
    public const float STANDARD = 1.0;

    private const float SMALLEST = 0.5;

    private const float LARGEST = 2.0;

    private const float STEP = 1.15;

    /**
     * Number of standard portions assumed before anything was said, so a
     * single verdict moves the general estimate only a little.
     */
    private const int PRIOR_WEIGHT = 2;

    /**
     * The portion of one recipe that suits someone.
     *
     * @param  list<array{0: PortionVerdict, 1: float}>  $history  Verdicts with the factor that was served, oldest first.
     */
    public function forRecipe(array $history): float
    {
        $factor = self::STANDARD;
        $lower = null;
        $upper = null;

        foreach ($history as [$verdict, $served]) {
            if ($verdict === PortionVerdict::TooSmall) {
                $lower = max($lower ?? $served, $served);
                $upper = $upper !== null && $upper <= $lower ? null : $upper;
            } elseif ($verdict === PortionVerdict::TooBig) {
                $upper = min($upper ?? $served, $served);
                $lower = $lower !== null && $lower >= $upper ? null : $lower;
            } else {
                $lower = $lower !== null && $lower >= $served ? null : $lower;
                $upper = $upper !== null && $upper <= $served ? null : $upper;
                $factor = $served;

                continue;
            }

            $factor = match (true) {
                $lower !== null && $upper !== null => sqrt($lower * $upper),
                $lower !== null => $lower * self::STEP,
                default => $upper / self::STEP,
            };
        }

        return $this->bounded($factor);
    }

    /**
     * The portion that suits someone for a recipe they said nothing about
     * yet: the geometric mean of what suits them elsewhere, drawn towards
     * the standard portion while little is known.
     *
     * @param  list<float>  $recipeFactors  What suits them for each recipe they gave a verdict on.
     */
    public function inGeneral(array $recipeFactors): float
    {
        $logs = array_sum(array_map(log(...), $recipeFactors));

        return $this->bounded(exp($logs / (count($recipeFactors) + self::PRIOR_WEIGHT)));
    }

    private function bounded(float $factor): float
    {
        return round(min(self::LARGEST, max(self::SMALLEST, $factor)), 2);
    }
}
