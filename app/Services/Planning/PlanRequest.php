<?php

namespace App\Services\Planning;

use App\Enums\Course;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Everything the generator needs to suggest recipes for a set of slots.
 */
final readonly class PlanRequest
{
    /**
     * @param  list<PlanSlot>  $slots  Slots to fill, in chronological order.
     * @param  list<int>  $tagIds  Only suggest recipes carrying one of these tags, if any are given.
     * @param  list<Course>  $courses  Only suggest recipes of these courses, if any are given.
     * @param  array<string, int>  $keep  Recipe ids the user locked, keyed by slot key.
     * @param  array<string, int>  $avoid  Recipe ids not to suggest again, keyed by slot key.
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $slots,
        public int $servings,
        public PlanPreferences $preferences,
        public User $user,
        public array $tagIds = [],
        public array $courses = [],
        public array $keep = [],
        public array $avoid = [],
    ) {}
}
