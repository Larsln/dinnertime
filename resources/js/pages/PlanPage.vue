<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Plus,
    ShoppingCart,
    Sparkles,
} from 'lucide-vue-next';
import { mealPlanApi, shoppingApi } from '@/api/planning';
import type { MealPlanEntry, PlanReason } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import { addDays, fromIsoDate, isoWeek, startOfWeek, toIsoDate, weekDays } from '@/lib/dates';
import { formatDay, formatLongDay } from '@/lib/format';
import { MEAL_TYPES } from '@/lib/meals';
import { mealsOf, sidesByMeal } from '@/lib/planSides';
import { slotKey } from '@/lib/planSlots';
import PlanEntryCard from '@/components/PlanEntryCard.vue';
import PlanEntryDialog from '@/components/PlanEntryDialog.vue';
import PlanWizard from '@/components/PlanWizard.vue';
import WeekStrip from '@/components/WeekStrip.vue';
import ActionMenu from '@/components/ui/ActionMenu.vue';
import AppButton from '@/components/ui/AppButton.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

/**
 * The meal plan of one week with manual entries, the leftover-aware
 * generator and the step on to the shopping list. Every meal tells why it
 * goes with the others and shows the sides served with it. Meals can be changed, moved, and the whole plan
 * from a day on pushed back or brought forward; the shopping list follows
 * on the server.
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
const reasons = useQuery({
    queryKey: ['meal-plan', 'reasons', period],
    queryFn: () => mealPlanApi.reasons(period.value),
});

const reasonsByEntry = computed(
    () => new Map<number, PlanReason[]>((reasons.data.value ?? []).map((row) => [row.entry_id, row.reasons])),
);

const entriesByDay = computed(() => {
    const groups = new Map<string, MealPlanEntry[]>(days.value.map((day) => [day, []]));
    const order = (entry: MealPlanEntry) => MEAL_TYPES.indexOf(entry.meal);

    for (const entry of mealsOf(entries.data.value ?? [])) {
        groups.get(entry.date)?.push(entry);
    }

    groups.forEach((list) => list.sort((a, b) => order(a) - order(b)));

    return groups;
});

const sides = computed(() => sidesByMeal(entries.data.value ?? []));

const addingFor = ref(today);
const isAdding = ref(false);
const editing = ref<MealPlanEntry | null>(null);
const isEditing = ref(false);
const isGenerating = ref(false);
const plannedSlots = computed(() => new Set((entries.data.value ?? []).map(slotKey)));

const daysWithEntries = computed(() => new Set((entries.data.value ?? []).map((entry) => entry.date)));

function scrollToDay(day: string): void {
    document.getElementById(`day-${day}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function refresh(): void {
    queryClient.invalidateQueries({ queryKey: ['meal-plan'] });
    queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
}

function shiftWeek(weeks: number): void {
    monday.value = addDays(monday.value, weeks * DAYS_PER_WEEK);
}

/**
 * Reload the plan after the wizard saved it and, if asked, go on to the
 * shopping list of the week.
 */
function onPlanSaved(createsList: boolean): void {
    refresh();

    if (createsList) {
        createList.mutate();
    }
}

function startAdding(day: string): void {
    addingFor.value = day;
    isAdding.value = true;
}

function startEditing(entry: MealPlanEntry): void {
    editing.value = entry;
    isEditing.value = true;
}

function dayActions(day: string) {
    return [
        {
            label: t('plan.shiftLater'),
            icon: ChevronsRight,
            select: () => shift.mutate({ from: day, days: 1 }),
        },
        {
            label: t('plan.shiftEarlier'),
            icon: ChevronsLeft,
            select: () => shift.mutate({ from: day, days: -1 }),
        },
    ];
}

const setCooked = useMutation({
    mutationFn: (change: { id: number; cooked: boolean }) =>
        mealPlanApi.update(change.id, { cooked: change.cooked }),
    onSuccess: refresh,
    onError: showError,
});

const move = useMutation({
    mutationFn: (change: { entry: MealPlanEntry; days: number }) =>
        mealPlanApi.update(change.entry.id, {
            date: toIsoDate(addDays(fromIsoDate(change.entry.date), change.days)),
        }),
    onSuccess: refresh,
    onError: showError,
});

const shift = useMutation({
    mutationFn: (change: { from: string; days: number }) => mealPlanApi.shift(change.from, change.days),
    onSuccess: (moved) => {
        refresh();
        showSuccess(t('plan.shifted', { count: moved.length }));
    },
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
        <AppButton @click="isGenerating = true"
            ><Sparkles class="size-4" />{{ t('plan.generate') }}</AppButton
        >
        <AppButton variant="primary" :disabled="createList.isPending.value" @click="createList.mutate()">
            <ShoppingCart class="size-4" />{{ t('plan.createList') }}
        </AppButton>
    </PageHeader>

    <WeekStrip :days="days" :today="today" :planned="daysWithEntries" @select="scrollToDay" />

    <div class="space-y-6">
        <section v-for="day in days" :id="`day-${day}`" :key="day" class="scroll-mt-4">
            <div class="mb-2 flex items-center gap-3">
                <h2 class="text-lg font-display font-bold">{{ formatLongDay(day, locale) }}</h2>
                <StatusBadge v-if="day === today" tone="info">{{ t('plan.today') }}</StatusBadge>
                <AppButton
                    class="ml-auto"
                    size="icon"
                    variant="ghost"
                    :aria-label="t('plan.addEntry')"
                    @click="startAdding(day)"
                >
                    <Plus class="size-4" />
                </AppButton>
                <ActionMenu :label="t('plan.dayActions')" :actions="dayActions(day)" />
            </div>
            <ul v-if="entriesByDay.get(day)?.length" class="space-y-2">
                <PlanEntryCard
                    v-for="entry in entriesByDay.get(day)"
                    :key="entry.id"
                    :entry="entry"
                    :sides="sides.get(entry.id)"
                    :reasons="reasonsByEntry.get(entry.id)"
                    @cooked="(cooked) => setCooked.mutate({ id: entry.id, cooked })"
                    @edit="startEditing(entry)"
                    @move="(days) => move.mutate({ entry, days })"
                    @remove="remove.mutate(entry.id)"
                    @remove-side="(id) => remove.mutate(id)"
                />
            </ul>
            <p
                v-else
                class="rounded-tile border border-dashed border-line-strong px-4 py-3 text-sm text-ink-muted"
            >
                {{ t('plan.nothingPlanned') }}
            </p>
        </section>
    </div>

    <PlanEntryDialog v-model:open="isAdding" :date="addingFor" />
    <PlanEntryDialog
        v-model:open="isEditing"
        :date="editing?.date ?? today"
        :entry="editing"
        :sides="editing ? sides.get(editing.id) : undefined"
    />

    <PlanWizard
        v-model:open="isGenerating"
        :days="days"
        :period="period"
        :planned="plannedSlots"
        @shift-week="shiftWeek"
        @saved="onPlanSaved"
    />
</template>
