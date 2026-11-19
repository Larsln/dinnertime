import { computed, ref, watch } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { dinnerApi } from '@/api/dinner';
import type { DinnerRound, DinnerRoundOptions } from '@/api/types';
import { phaseOf, type DinnerPhase } from '@/lib/dinnerRound';
import { useToasts } from './useToasts';

export const DINNER_ROUND_KEY = ['dinner-round'];

const POLL_MILLISECONDS = 5000;
const POLLED_PHASES: DinnerPhase[] = ['waiting', 'second', 'exhausted'];

/**
 * Today's dinner round with everything a member can do in it. The round
 * reloads when the server announces a change, and at a slow pace while
 * the next step is up to the others, for servers without realtime.
 */
export function useDinnerRound() {
    const queryClient = useQueryClient();
    const { showError } = useToasts();

    const declined = ref<Set<number>>(new Set());

    const current = useQuery({
        queryKey: DINNER_ROUND_KEY,
        queryFn: dinnerApi.current,
        refetchInterval: (query) =>
            POLLED_PHASES.includes(phaseOf(query.state.data, declined.value)) ? POLL_MILLISECONDS : false,
    });

    const round = computed(() => current.data.value ?? null);
    const phase = computed(() => phaseOf(round.value, declined.value));

    watch(
        () => round.value?.id,
        () => {
            declined.value = new Set();
        },
    );

    function store(next: DinnerRound | null): void {
        queryClient.setQueryData(DINNER_ROUND_KEY, next);
    }

    function reload(error: unknown): void {
        showError(error);
        queryClient.invalidateQueries({ queryKey: DINNER_ROUND_KEY });
    }

    const start = useMutation({
        mutationFn: (options: DinnerRoundOptions) => dinnerApi.start(options),
        onSuccess: store,
        onError: showError,
    });

    const vote = useMutation({
        mutationFn: (choice: { roundId: number; recipeId: number; liked: boolean; isSecondLook: boolean }) =>
            dinnerApi.vote(choice.roundId, choice.recipeId, choice.liked),
        onSuccess: (next, choice) => {
            if (choice.isSecondLook && !choice.liked) {
                declined.value = new Set([...declined.value, choice.recipeId]);
            }

            store(next);
        },
        onError: reload,
    });

    /**
     * Say yes or no to a recipe of the running round. A no in the second
     * look is remembered here, as the server already holds that vote.
     */
    function decide(recipeId: number, liked: boolean): void {
        if (round.value && !vote.isPending.value) {
            vote.mutate({ roundId: round.value.id, recipeId, liked, isSecondLook: phase.value === 'second' });
        }
    }

    const plan = useMutation({
        mutationFn: (roundId: number) => dinnerApi.plan(roundId),
        onSuccess: () => {
            store(null);
            queryClient.invalidateQueries({ queryKey: ['meal-plan'] });
        },
        onError: reload,
    });

    const close = useMutation({
        mutationFn: (roundId: number) => dinnerApi.close(roundId),
        onSuccess: () => store(null),
        onError: reload,
    });

    return { current, round, phase, declined, start, vote, decide, plan, close };
}
