<?php

namespace App\Services\Planning;

/**
 * What the household wants a suggested plan to favor.
 */
final readonly class PlanPreferences
{
    /**
     * @param  bool  $useLeftovers  Choose recipes that use up opened packages and the pantry.
     * @param  bool  $quickOnWeekdays  From Monday to Friday only suggest quick recipes.
     * @param  bool  $preferNotRecent  Favor recipes that were not planned for a long time.
     * @param  bool  $preferFavorites  Favor favorites and well rated recipes.
     * @param  bool  $variety  Avoid repeats and the same cuisine on consecutive days.
     */
    public function __construct(
        public bool $useLeftovers = true,
        public bool $quickOnWeekdays = false,
        public bool $preferNotRecent = true,
        public bool $preferFavorites = false,
        public bool $variety = true,
    ) {}

    /**
     * Build the preferences from request data, keeping the default of
     * every key that is missing.
     *
     * @param  array<string, bool>  $values
     */
    public static function fromArray(array $values): self
    {
        $defaults = new self;

        return new self(
            $values['use_leftovers'] ?? $defaults->useLeftovers,
            $values['quick_on_weekdays'] ?? $defaults->quickOnWeekdays,
            $values['prefer_not_recent'] ?? $defaults->preferNotRecent,
            $values['prefer_favorites'] ?? $defaults->preferFavorites,
            $values['variety'] ?? $defaults->variety,
        );
    }
}
