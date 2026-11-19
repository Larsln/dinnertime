import type { DinnerCard, DinnerRound } from '@/api/types';

/**
 * Where a member stands in a dinner round: no round, an empty one, cards
 * left to vote on, waiting for the others, a second look at what the
 * others liked, nothing left to decide, or a match.
 */
export type DinnerPhase = 'none' | 'empty' | 'voting' | 'waiting' | 'second' | 'exhausted' | 'matched';

/**
 * Cards the member has not voted on yet.
 */
export function openCards(round: DinnerRound): DinnerCard[] {
    return round.cards.filter((card) => card.my_vote === null);
}

/**
 * Cards somebody else liked that the member has not turned down again.
 *
 * @param declined Ids of the recipes turned down in the second pass.
 */
export function secondChanceCards(round: DinnerRound, declined: ReadonlySet<number>): DinnerCard[] {
    return round.cards.filter((card) => card.liked_by_others && !declined.has(card.recipe.id));
}

/**
 * Names of the members who started voting but are not through all cards.
 */
export function membersBehind(round: DinnerRound): string[] {
    return round.members
        .filter((member) => member.voted_count < round.cards.length)
        .map((member) => member.name);
}

export function phaseOf(round: DinnerRound | null | undefined, declined: ReadonlySet<number>): DinnerPhase {
    if (!round) {
        return 'none';
    }

    if (round.status === 'matched') {
        return 'matched';
    }

    if (round.cards.length === 0) {
        return 'empty';
    }

    if (openCards(round).length > 0) {
        return 'voting';
    }

    if (round.status === 'voting') {
        return 'waiting';
    }

    return secondChanceCards(round, declined).length > 0 ? 'second' : 'exhausted';
}

/**
 * Preparation and cooking time of a recipe together.
 */
export function totalMinutes(recipe: { prep_minutes: number | null; cook_minutes: number | null }): number {
    return (recipe.prep_minutes ?? 0) + (recipe.cook_minutes ?? 0);
}
