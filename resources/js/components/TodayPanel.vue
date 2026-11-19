<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { mealPlanApi } from '@/api/planning';
import { useDinnerRound } from '@/composables/useDinnerRound';
import { addDays, toIsoDate } from '@/lib/dates';
import { isPlanned, nextMeal } from '@/lib/upNext';
import DinnerPrompt from '@/components/DinnerPrompt.vue';
import DinnerStartDialog from '@/components/DinnerStartDialog.vue';
import UpNextBanner from '@/components/UpNextBanner.vue';

/**
 * What matters today: the next meal to cook from the plan of the coming
 * two weeks, and the way to decide tonight's dinner together while
 * nothing is planned for today.
 */
const DAYS_AHEAD = 13;

const router = useRouter();

const now = new Date();
const today = toIsoDate(now);
const period = { from: today, to: toIsoDate(addDays(now, DAYS_AHEAD)) };

const plan = useQuery({ queryKey: ['meal-plan', period], queryFn: () => mealPlanApi.list(period) });
const { current, round } = useDinnerRound();

const isStarting = ref(false);

const upNext = computed(() => nextMeal(plan.data.value ?? [], today));
const showsPrompt = computed(
    () => plan.data.value !== undefined && !current.isPending.value && !isPlanned(plan.data.value, today),
);
</script>

<template>
    <div v-if="showsPrompt || upNext" class="mb-6 space-y-4">
        <DinnerPrompt v-if="showsPrompt" :round="round" @start="isStarting = true" />
        <UpNextBanner v-if="upNext" :entry="upNext" />
        <DinnerStartDialog v-model:open="isStarting" @started="router.push({ name: 'dinner' })" />
    </div>
</template>
