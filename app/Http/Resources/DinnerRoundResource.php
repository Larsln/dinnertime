<?php

namespace App\Http\Resources;

use App\Models\DinnerRound;
use App\Models\DinnerRoundCard;
use App\Models\DinnerRoundVote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin DinnerRound
 */
class DinnerRoundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Other members' votes stay hidden while the round is running, except
     * for the recipes only they liked once everyone is through: those get
     * a second chance.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()->id;
        $votes = $this->votes->groupBy('recipe_id');
        $mine = $this->votes->where('user_id', $userId)->keyBy('recipe_id');

        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'is_solo' => $this->is_solo,
            'status' => $this->status(),
            'match' => $this->match === null ? null : new RecipeSummaryResource($this->match),
            'cards' => $this->cards->map(fn (DinnerRoundCard $card) => [
                'recipe' => new RecipeSummaryResource($card->recipe),
                'missing_count' => $card->missing_count,
                'my_vote' => $mine->get($card->recipe_id)?->liked,
                'liked_by_others' => $this->isUndecided()
                    && $this->likedByOthers($votes->get($card->recipe_id, collect()), $userId),
            ])->all(),
            'members' => $this->votes->groupBy('user_id')->map(fn (Collection $own) => [
                'name' => $own->first()->user->name,
                'voted_count' => $own->count(),
            ])->values()->all(),
        ];
    }

    /**
     * `matched`, `undecided` once every member who takes part went through
     * all recipes without a match, otherwise `voting`.
     */
    private function status(): string
    {
        return match (true) {
            $this->matched_recipe_id !== null => 'matched',
            $this->isUndecided() => 'undecided',
            default => 'voting',
        };
    }

    private function isUndecided(): bool
    {
        $counts = $this->votes->groupBy('user_id')->map->count();

        return $this->matched_recipe_id === null
            && $this->cards->isNotEmpty()
            && $counts->count() >= $this->likesToMatch()
            && $counts->every(fn (int $count) => $count >= $this->cards->count());
    }

    /**
     * @param  Collection<int, DinnerRoundVote>  $votes
     */
    private function likedByOthers(Collection $votes, int $userId): bool
    {
        return $votes->contains(fn (DinnerRoundVote $vote) => $vote->liked && $vote->user_id !== $userId);
    }
}
