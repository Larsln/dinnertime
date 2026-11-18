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
     */
    public function __construct(
        public PlanSlot $slot,
        public Recipe $recipe,
        public bool $kept,
    ) {}
}
