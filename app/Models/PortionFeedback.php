<?php

namespace App\Models;

use App\Enums\PortionVerdict;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What one household member said about the portion of a meal, together
 * with the share of a standard portion that was cooked for them. It
 * outlives the plan entry, so later meals of the recipe learn from it.
 *
 * @property PortionVerdict $verdict
 * @property float $served_factor
 */
#[Fillable(['user_id', 'recipe_id', 'meal_plan_entry_id', 'verdict', 'served_factor'])]
class PortionFeedback extends Model
{
    protected $table = 'portion_feedback';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verdict' => PortionVerdict::class,
            'served_factor' => 'float',
        ];
    }
}
