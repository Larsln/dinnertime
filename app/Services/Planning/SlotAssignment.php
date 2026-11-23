<?php

namespace App\Services\Planning;

use App\Models\Recipe;

/**
 * Recipe suggested for a slot.
 */
final readonly class SlotAssignment
{
    /**
     * @param  bool  $kept  Whether the user locked the recipe, as opposed to the generator choosing it.
     * @param  list<array<string, mixed>>  $reasons  Why the recipe fits the plan, see PlanExplainer.
     */
    public function __construct(
        public PlanSlot $slot,
        public Recipe $recipe,
        public bool $kept,
        public array $reasons = [],
    ) {}

    /**
     * @param  list<array<string, mixed>>  $reasons
     */
    public function withReasons(array $reasons): self
    {
        return new self($this->slot, $this->recipe, $this->kept, $reasons);
    }
}
