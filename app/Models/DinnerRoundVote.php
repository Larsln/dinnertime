<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yes or no of a household member to a recipe of a dinner round.
 *
 * @property bool $liked
 */
#[Fillable(['dinner_round_id', 'recipe_id', 'user_id', 'liked'])]
class DinnerRoundVote extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'liked' => 'boolean',
        ];
    }

    /**
     * Member who voted.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
