<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { ChevronLeft, ChevronRight, Plus, ShoppingCart, Sparkles, Trash2 } from 'lucide-vue-next';
import { mealPlanApi, shoppingApi } from '@/api/planning';
import type { MealPlanEntry } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import { addDays, startOfWeek, toIsoDate, weekDays } from '@/lib/dates';
import { formatDay } from '@/lib/format';
import { MEAL_TYPES } from '@/lib/meals';
import { presetSlots, slotKey, type PlanSlot } from '@/lib/planSlots';
import PlanEntryDialog from '@/components/PlanEntryDialog.vue';
import PlanMatrix from '@/components/PlanMatrix.vue';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import NumberField from '@/components/ui/NumberField.vue';
import PageHeader from '@/components/ui/PageHeader.vue';

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
        :subtitle="`${formatDay(period.from, locale)} – ${formatDay(period.to, locale)}`"
    >
        <AppButton size="icon" :aria-label="t('plan.previousWeek')" @click="shiftWeek(-1)"
            ><ChevronLeft class="size-4"
        /></AppButton>
        <AppButton size="icon" :aria-label="t('plan.nextWeek')" @click="shiftWeek(1)"
            ><ChevronRight class="size-4"
        /></AppButton>
        <AppButton @click="openGenerator"><Sparkles class="size-4" />{{ t('plan.generate') }}</AppButton>
        <AppButton variant="primary" :disabled="createList.isPending.value" @click="createList.mutate()">
            <ShoppingCart class="size-4" />{{ t('plan.createList') }}
        </AppButton>
    </PageHeader>

    <div class="space-y-3">
        <section
            v-for="day in days"
            :key="day"
            class="rounded-2xl border bg-white p-4"
            :class="day === today ? 'border-emerald-300' : 'border-stone-200'"
        >
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">{{ formatDay(day, locale) }}</h2>
                <AppButton
                    size="icon"
                    variant="ghost"
                    :aria-label="t('plan.addEntry')"
                    @click="startAdding(day)"
                >
                    <Plus class="size-4" />
                </AppButton>
            </div>
            <ul class="divide-y divide-stone-100">
                <li
                    v-for="entry in entriesByDay.get(day)"
                    :key="entry.id"
                    class="flex items-center gap-3 py-2 text-sm"
                >
                    <CheckboxField
                        :model-value="entry.cooked_at !== null"
                        :aria-label="t('plan.cooked')"
                        @update:model-value="(cooked) => setCooked.mutate({ id: entry.id, cooked })"
                    />
                    <span class="w-24 shrink-0 text-stone-500">{{ t(`meals.${entry.meal}`) }}</span>
                    <RouterLink
                        :to="{ name: 'recipe', params: { id: entry.recipe.id } }"
                        class="min-w-0 flex-1 truncate font-medium hover:text-emerald-700"
                        :class="{ 'text-stone-400 line-through': entry.cooked_at !== null }"
                    >
                        {{ entry.recipe.title }}
                    </RouterLink>
                    <span class="shrink-0 text-stone-500">{{
                        t('plan.servings', { count: entry.servings })
                    }}</span>
                    <AppButton
                        size="icon"
                        variant="ghost"
                        :aria-label="t('common.delete')"
                        @click="remove.mutate(entry.id)"
                    >
                        <Trash2 class="size-4" />
                    </AppButton>
                </li>
            </ul>
        </section>
    </div>

    <PlanEntryDialog v-model:open="isAdding" :date="addingFor" />

    <DialogPanel
        v-model:open="isGenerating"
        :title="t('plan.generate')"
        :description="t('plan.generateDescription')"
        :close-label="t('common.close')"
    >
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
