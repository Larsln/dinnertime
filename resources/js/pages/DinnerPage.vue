<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import type { DinnerCard } from '@/api/types';
import { useDinnerRound } from '@/composables/useDinnerRound';
import { membersBehind, openCards, secondChanceCards } from '@/lib/dinnerRound';
import DinnerDeck from '@/components/DinnerDeck.vue';
import DinnerMatch from '@/components/DinnerMatch.vue';
import DinnerStartDialog from '@/components/DinnerStartDialog.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';

/**
 * Decides tonight's dinner: the members of the household say yes or no to
 * the same recipes one at a time, and the first one enough of them like is
 * the match that can go on today's plan.
 */
const { t, locale } = useI18n();
const router = useRouter();
const { current, round, phase, declined, vote, decide, plan, close } = useDinnerRound();

const isStarting = ref(false);
const isEnding = ref(false);

const isBusy = computed(() => vote.isPending.value || plan.isPending.value || close.isPending.value);

/**
 * The card to decide on with its place in the pass that is running.
 */
const deck = computed<{ card: DinnerCard; position: number; total: number } | null>(() => {
    if (!round.value) {
        return null;
    }

    const isSecondLook = phase.value === 'second';
    const remaining = isSecondLook ? secondChanceCards(round.value, declined.value) : openCards(round.value);
    const total = isSecondLook
        ? round.value.cards.filter((card) => card.liked_by_others).length
        : round.value.cards.length;
    const card = remaining[0];

    return card ? { card, position: total - remaining.length + 1, total } : null;
});

const waitingFor = computed(() => {
    const names = round.value ? membersBehind(round.value) : [];

    return names.length > 0
        ? t('dinner.waitingFor', { names: new Intl.ListFormat(locale.value).format(names) })
        : t('dinner.waiting');
});

function planMatch(): void {
    const recipe = round.value?.match;

    if (round.value && recipe) {
        plan.mutate(round.value.id, {
            onSuccess: () => router.push({ name: 'recipe', params: { id: recipe.id } }),
        });
    }
}

function closeRound(startsAgain = false): void {
    if (round.value) {
        close.mutate(round.value.id, {
            onSuccess: () => {
                isStarting.value = startsAgain;
            },
        });
    }
}
</script>

<template>
    <PageHeader :title="t('dinner.title')">
        <AppButton
            v-if="phase === 'voting' || phase === 'waiting' || phase === 'second'"
            variant="ghost"
            @click="isEnding = true"
        >
            {{ t('dinner.endRound') }}
        </AppButton>
    </PageHeader>

    <template v-if="!current.isPending.value">
        <DinnerMatch
            v-if="phase === 'matched' && round?.match"
            :recipe="round.match"
            :disabled="isBusy"
            @plan="planMatch"
            @discard="closeRound()"
        />
        <template v-else-if="deck && (phase === 'voting' || phase === 'second')">
            <p v-if="phase === 'second'" class="mb-4 text-center font-medium text-ink">
                {{ t('dinner.secondLook') }}
            </p>
            <DinnerDeck
                :card="deck.card"
                :position="deck.position"
                :total="deck.total"
                :disabled="isBusy || isEnding"
                @decide="(liked) => decide(deck!.card.recipe.id, liked)"
            />
        </template>
        <EmptyState v-else-if="phase === 'waiting'" :title="waitingFor" :hint="t('dinner.waitingHint')" />
        <EmptyState
            v-else-if="phase === 'exhausted'"
            :title="t('dinner.noMatch')"
            :hint="t('dinner.noMatchHint')"
        >
            <AppButton variant="primary" :disabled="isBusy" @click="closeRound(true)">
                {{ t('dinner.newRound') }}
            </AppButton>
        </EmptyState>
        <EmptyState v-else-if="phase === 'empty'" :title="t('dinner.empty')" :hint="t('dinner.emptyHint')">
            <AppButton variant="primary" :disabled="isBusy" @click="closeRound()">
                {{ t('dinner.closeRound') }}
            </AppButton>
        </EmptyState>
        <EmptyState v-else :title="t('dinner.none')" :hint="t('dinner.promptHint')">
            <AppButton variant="primary" @click="isStarting = true">{{ t('dinner.start') }}</AppButton>
        </EmptyState>
    </template>

    <DinnerStartDialog v-model:open="isStarting" />
    <ConfirmDialog
        v-model:open="isEnding"
        :title="t('dinner.endRound')"
        :description="t('dinner.endDescription')"
        :confirm-label="t('dinner.endRound')"
        :cancel-label="t('common.cancel')"
        @confirm="closeRound()"
    />
</template>
