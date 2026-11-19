<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ArrowRight, Utensils } from 'lucide-vue-next';
import type { DinnerRound } from '@/api/types';
import { phaseOf } from '@/lib/dinnerRound';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * Invitation to decide tonight's dinner together, or the state of the
 * round that is running already.
 */
const props = defineProps<{ round: DinnerRound | null }>();

defineEmits<{ start: [] }>();

const { t } = useI18n();

const NO_SECOND_LOOKS: ReadonlySet<number> = new Set();

const state = computed(() => {
    const phase = phaseOf(props.round, NO_SECOND_LOOKS);

    if (phase === 'matched') {
        return {
            title: t('dinner.matchFound', { title: props.round?.match?.title ?? '' }),
            action: t('dinner.showMatch'),
        };
    }

    if (phase === 'voting' || phase === 'second') {
        return { title: t('dinner.yourTurn'), action: t('dinner.continue') };
    }

    return { title: t('dinner.running'), action: t('dinner.open') };
});
</script>

<template>
    <section
        class="flex flex-wrap items-center gap-x-6 gap-y-4 rounded-card border border-line bg-accent-soft p-5"
    >
        <span
            class="hidden size-12 shrink-0 items-center justify-center rounded-full bg-surface text-accent sm:flex"
        >
            <Utensils class="size-6" />
        </span>
        <div class="min-w-48 flex-1">
            <template v-if="round">
                <p class="text-xs font-bold tracking-wider text-accent-deep uppercase">
                    {{ t('dinner.title') }}
                </p>
                <h2 class="font-display text-2xl font-bold tracking-tight text-ink">{{ state.title }}</h2>
            </template>
            <template v-else>
                <h2 class="font-display text-2xl font-bold tracking-tight text-ink">
                    {{ t('dinner.title') }}
                </h2>
                <p class="mt-1 text-sm text-ink-muted">{{ t('dinner.promptHint') }}</p>
            </template>
        </div>
        <RouterLink v-if="round" v-slot="{ navigate }" :to="{ name: 'dinner' }" custom>
            <AppButton variant="primary" @click="navigate">
                {{ state.action }}<ArrowRight class="size-4" />
            </AppButton>
        </RouterLink>
        <AppButton v-else variant="primary" @click="$emit('start')">{{ t('dinner.start') }}</AppButton>
    </section>
</template>
