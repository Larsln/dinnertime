<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { mealPlanApi } from '@/api/planning';
import type { MealPlanEntry, PortionVerdict } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import { useSessionStore } from '@/stores/session';
import ChoiceChips from '@/components/ui/ChoiceChips.vue';

/**
 * Lets the signed-in user say how the portion of a meal was for them.
 * Later meals are cooked with a portion that follows these verdicts.
 */
const VERDICTS: PortionVerdict[] = ['too_small', 'right', 'too_big'];

const props = defineProps<{ entry: MealPlanEntry }>();

const { t } = useI18n();
const { showError } = useToasts();
const queryClient = useQueryClient();
const session = useSessionStore();

const options = computed(() => VERDICTS.map((value) => ({ value, label: t(`plan.portion.${value}`) })));

const save = useMutation({
    mutationFn: (verdict: PortionVerdict | null) => mealPlanApi.setPortionFeedback(props.entry.id, verdict),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['meal-plan'] });
        queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
    },
    onError: showError,
});

const verdict = computed({
    get: () =>
        props.entry.portion_feedback.find((feedback) => feedback.user_id === session.user?.id)?.verdict ??
        null,
    set: (value: PortionVerdict | null) => save.mutate(value),
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 text-xs text-ink-muted">
        <span>{{ t('plan.portion.label') }}</span>
        <ChoiceChips
            v-model="verdict"
            :label="t('plan.portion.label')"
            :options="options"
            :disabled="save.isPending.value"
        />
    </div>
</template>
