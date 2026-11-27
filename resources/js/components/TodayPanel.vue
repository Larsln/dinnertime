<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { mealPlanApi } from '@/api/planning';
import { useDinnerRound } from '@/composables/useDinnerRound';
import { addDays, toIsoDate } from '@/lib/dates';
import { sidesByMeal } from '@/lib/planSides';
import { isPlanned, nextMeal } from '@/lib/upNext';
import DinnerPrompt from '@/components/DinnerPrompt.vue';
import DinnerStartDialog from '@/components/DinnerStartDialog.vue';
import UpNextBanner from '@/components/UpNextBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import { Layers } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

/**
 * What matters today: the next meal to cook from the plan of the coming
 * two weeks, and the way to decide tonight's dinner together while
 * nothing is planned for today, with a small way to do so anyway.
 */
const DAYS_AHEAD = 13;

const router = useRouter();
const { t } = useI18n();

const now = new Date();
const today = toIsoDate(now);
const period = { from: today, to: toIsoDate(addDays(now, DAYS_AHEAD)) };

const plan = useQuery({ queryKey: ['meal-plan', period], queryFn: () => mealPlanApi.list(period) });
const { current, round } = useDinnerRound();

const isStarting = ref(false);

const upNext = computed(() => nextMeal(plan.data.value ?? [], today));
const sides = computed(() => sidesByMeal(plan.data.value ?? []));
const isLoaded = computed(() => plan.data.value !== undefined && !current.isPending.value);
const showsPrompt = computed(
    () => isLoaded.value && (round.value !== null || !isPlanned(plan.data.value ?? [], today)),
);
const showsShortcut = computed(() => isLoaded.value && !showsPrompt.value);
</script>

<template>
    <div v-if="showsPrompt || upNext" class="mb-6 space-y-4">
        <DinnerPrompt v-if="showsPrompt" :round="round" @start="isStarting = true" />
        <UpNextBanner v-if="upNext" :entry="upNext" :sides="sides.get(upNext.id)" />
        <AppButton v-if="showsShortcut" variant="ghost" size="sm" @click="isStarting = true">
            <Layers class="size-4" />{{ t('dinner.shortcut') }}
        </AppButton>
        <DinnerStartDialog v-model:open="isStarting" @started="router.push({ name: 'dinner' })" />
    </div>
</template>
