<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { ChevronLeft, ChevronRight, Plus, ShoppingCart, Sparkles } from 'lucide-vue-next';
import { mealPlanApi, shoppingApi } from '@/api/planning';
import type { MealPlanEntry } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import { addDays, isoWeek, startOfWeek, toIsoDate, weekDays } from '@/lib/dates';
import { formatDay, formatLongDay } from '@/lib/format';
import { MEAL_TYPES } from '@/lib/meals';
import { presetSlots, slotKey, type PlanSlot } from '@/lib/planSlots';
import PlanEntryCard from '@/components/PlanEntryCard.vue';
import PlanEntryDialog from '@/components/PlanEntryDialog.vue';
import PlanMatrix from '@/components/PlanMatrix.vue';
import WeekStrip from '@/components/WeekStrip.vue';
import AppButton from '@/components/ui/AppButton.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import NumberField from '@/components/ui/NumberField.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

/**
 * The meal plan of one week with manual entries, the leftover-aware
 * generator and the step on to the shopping list.
 */
const DAYS_PER_WEEK = 7;

const { t, locale } = useI18n();
const router = useRouter();
const queryClient = useQueryClient();
const { showError, showSuccess } = useToasts();

const monday = ref(startOfWeek(new Date()));
const period = computed(() => ({
    from: toIsoDate(monday.value),
    to: toIsoDate(addDays(monday.value, DAYS_PER_WEEK - 1)),
}));
const days = computed(() => weekDays(monday.value).map(toIsoDate));
const today = toIsoDate(new Date());

const entries = useQuery({ queryKey: ['meal-plan', period], queryFn: () => mealPlanApi.list(period.value) });

const entriesByDay = computed(() => {
    const groups = new Map<string, MealPlanEntry[]>(days.value.map((day) => [day, []]));
    const order = (entry: MealPlanEntry) => MEAL_TYPES.indexOf(entry.meal);

    for (const entry of entries.data.value ?? []) {
        groups.get(entry.date)?.push(entry);
    }

    groups.forEach((list) => list.sort((a, b) => order(a) - order(b)));

    return groups;
});

const addingFor = ref(today);
const isAdding = ref(false);
const isGenerating = ref(false);
const slots = ref<PlanSlot[]>([]);
const plannedSlots = computed(() => new Set((entries.data.value ?? []).map(slotKey)));
const servings = ref<number | null>(2);

const daysWithEntries = computed(() => new Set((entries.data.value ?? []).map((entry) => entry.date)));

function scrollToDay(day: string): void {
    document.getElementById(`day-${day}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function refresh(): void {
    queryClient.invalidateQueries({ queryKey: ['meal-plan'] });
}

function shiftWeek(weeks: number): void {
    monday.value = addDays(monday.value, weeks * DAYS_PER_WEEK);
}

/**
 * Open the generator with the open dinners of the week ticked.
 */
function openGenerator(): void {
    slots.value = presetSlots(days.value, 'all', ['dinner'], plannedSlots.value);
    isGenerating.value = true;
}

watch(plannedSlots, (planned) => {
    if (isGenerating.value) {
        slots.value = presetSlots(days.value, 'all', ['dinner'], planned);
    }
});

function startAdding(day: string): void {
    addingFor.value = day;
    isAdding.value = true;
}

const generate = useMutation({
    mutationFn: () => mealPlanApi.generate(period.value, slots.value, servings.value ?? 2),
    onSuccess: (added) => {
        refresh();
        isGenerating.value = false;
        showSuccess(t('plan.generated', { count: added.length }));
    },
    onError: showError,
});

const setCooked = useMutation({
    mutationFn: (change: { id: number; cooked: boolean }) =>
        mealPlanApi.update(change.id, { cooked: change.cooked }),
    onSuccess: refresh,
    onError: showError,
});

const remove = useMutation({
    mutationFn: (id: number) => mealPlanApi.remove(id),
    onSuccess: refresh,
    onError: showError,
});

const createList = useMutation({
    mutationFn: () => shoppingApi.sync(period.value),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
        router.push({ name: 'shopping' });
    },
    onError: showError,
});
</script>

<template>
    <PageHeader
        :title="t('nav.plan')"
        :subtitle="`${t('plan.week', { number: isoWeek(monday) })} · ${formatDay(period.from, locale)} – ${formatDay(period.to, locale)}`"
    >
        <AppButton size="icon" :aria-label="t('plan.previousWeek')" @click="shiftWeek(-1)"
            ><ChevronLeft class="size-4"
        /></AppButton>
        <AppButton size="icon" :aria-label="t('plan.nextWeek')" @click="shiftWeek(1)"
            ><ChevronRight class="size-4"
        /></AppButton>
        <AppButton v-if="!days.includes(today)" @click="monday = startOfWeek(new Date())">
            {{ t('plan.currentWeek') }}
        </AppButton>
        <AppButton @click="openGenerator"><Sparkles class="size-4" />{{ t('plan.generate') }}</AppButton>
        <AppButton variant="primary" :disabled="createList.isPending.value" @click="createList.mutate()">
            <ShoppingCart class="size-4" />{{ t('plan.createList') }}
        </AppButton>
    </PageHeader>

    <WeekStrip :days="days" :today="today" :planned="daysWithEntries" @select="scrollToDay" />

    <div class="space-y-6">
        <section v-for="day in days" :id="`day-${day}`" :key="day" class="scroll-mt-4">
            <div class="mb-2 flex items-center gap-3">
                <h2 class="text-lg font-semibold">{{ formatLongDay(day, locale) }}</h2>
                <StatusBadge v-if="day === today" tone="success">{{ t('plan.today') }}</StatusBadge>
                <AppButton
                    class="ml-auto"
                    size="icon"
                    variant="ghost"
                    :aria-label="t('plan.addEntry')"
                    @click="startAdding(day)"
                >
                    <Plus class="size-4" />
                </AppButton>
            </div>
            <ul v-if="entriesByDay.get(day)?.length" class="space-y-2">
                <PlanEntryCard
                    v-for="entry in entriesByDay.get(day)"
                    :key="entry.id"
                    :entry="entry"
                    @cooked="(cooked) => setCooked.mutate({ id: entry.id, cooked })"
                    @remove="remove.mutate(entry.id)"
                />
            </ul>
            <p
                v-else
                class="rounded-2xl border border-dashed border-stone-300 px-4 py-3 text-sm text-stone-500"
            >
                {{ t('plan.nothingPlanned') }}
            </p>
        </section>
    </div>

    <PlanEntryDialog v-model:open="isAdding" :date="addingFor" />

    <DialogPanel
        v-model:open="isGenerating"
        :title="t('plan.generate')"
        :description="t('plan.generateDescription')"
        :close-label="t('common.close')"
    >
        <div class="flex items-center justify-between gap-2">
            <AppButton size="icon" :aria-label="t('plan.previousWeek')" @click="shiftWeek(-1)">
                <ChevronLeft class="size-4" />
            </AppButton>
            <p class="text-sm font-medium">
                {{ formatDay(period.from, locale) }} – {{ formatDay(period.to, locale) }}
            </p>
            <AppButton size="icon" :aria-label="t('plan.nextWeek')" @click="shiftWeek(1)">
                <ChevronRight class="size-4" />
            </AppButton>
        </div>
        <PlanMatrix v-model="slots" :days="days" :planned="plannedSlots" />
        <NumberField v-model="servings" :label="t('recipes.servings')" :min="1" :max="50" steppers />
        <template #actions>
            <AppButton
                variant="primary"
                :disabled="slots.length === 0 || generate.isPending.value"
                @click="generate.mutate()"
            >
                {{ t('plan.generateCount', { count: slots.length }) }}
            </AppButton>
        </template>
    </DialogPanel>
</template>
