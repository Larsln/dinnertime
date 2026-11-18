<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation } from '@tanstack/vue-query';
import { Check, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { mealPlanApi, type Period } from '@/api/planning';
import type { CourseKey } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import { formatDay } from '@/lib/format';
import { presetSlots, type PlanSlot } from '@/lib/planSlots';
import {
    defaultPreferences,
    mergeSuggestion,
    pinsForReroll,
    pinsForRerollAll,
    type PreviewRow,
    type SuggestBody,
} from '@/lib/planWizard';
import PlanMatrix from '@/components/PlanMatrix.vue';
import PlanPreferencesForm from '@/components/PlanPreferencesForm.vue';
import PlanPreview from '@/components/PlanPreview.vue';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import NumberField from '@/components/ui/NumberField.vue';

/**
 * Plans a week in three steps: choose the days and meals, state what the
 * suggestion should favor, then review the suggestion and save it.
 */
const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{ days: string[]; period: Period; planned: Set<string> }>();

const emit = defineEmits<{ shiftWeek: [weeks: number]; saved: [createList: boolean] }>();

const STEPS = ['days', 'preferences', 'preview'] as const;
const DEFAULT_SERVINGS = 2;

const { t, locale } = useI18n();
const { showError, showSuccess } = useToasts();

const step = ref<(typeof STEPS)[number]>('days');
const slots = ref<PlanSlot[]>([]);
const servings = ref<number | null>(DEFAULT_SERVINGS);
const preferences = ref(defaultPreferences());
const courses = ref<CourseKey[]>([]);
const tagIds = ref<number[]>([]);
const rows = ref<PreviewRow[]>([]);
const candidateCount = ref(0);
const createList = ref(true);

const stepIndex = computed(() => STEPS.indexOf(step.value));

function selectOpenDinners(): void {
    slots.value = presetSlots(props.days, 'all', ['dinner'], props.planned);
}

watch(open, (isOpen) => {
    if (isOpen) {
        step.value = 'days';
        rows.value = [];
        selectOpenDinners();
    }
});

watch(
    () => props.planned,
    () => {
        if (open.value && step.value === 'days') {
            selectOpenDinners();
        }
    },
);

function body(pins: Pick<SuggestBody, 'keep' | 'avoid'>): SuggestBody {
    return {
        ...props.period,
        slots: slots.value,
        servings: servings.value ?? DEFAULT_SERVINGS,
        preferences: preferences.value,
        tag_ids: tagIds.value,
        courses: courses.value,
        ...pins,
    };
}

const suggest = useMutation({
    mutationFn: (pins: Pick<SuggestBody, 'keep' | 'avoid'>) => mealPlanApi.suggest(body(pins)),
    onSuccess: (suggestion) => {
        rows.value = mergeSuggestion(rows.value, suggestion.data);
        candidateCount.value = suggestion.meta.candidate_count;
        step.value = 'preview';
    },
    onError: showError,
});

const save = useMutation({
    mutationFn: () =>
        mealPlanApi.saveBatch(
            rows.value.map((row) => ({
                date: row.date,
                meal: row.meal,
                recipe_id: row.recipe.id,
                servings: servings.value ?? DEFAULT_SERVINGS,
            })),
        ),
    onSuccess: (entries) => {
        showSuccess(t('plan.generated', { count: entries.length }));
        open.value = false;
        emit('saved', createList.value);
    },
    onError: showError,
});
</script>

<template>
    <DialogPanel
        v-model:open="open"
        size="lg"
        :title="t('plan.wizardTitle')"
        :close-label="t('common.close')"
    >
        <ol class="grid grid-cols-3 gap-2 text-sm">
            <li
                v-for="(name, index) in STEPS"
                :key="name"
                class="flex items-center justify-center gap-2 rounded-full border px-3 py-1.5"
                :class="
                    name === step
                        ? 'border-emerald-600 bg-emerald-50 font-medium text-emerald-800'
                        : 'border-stone-200 text-stone-500'
                "
            >
                <Check v-if="index < stepIndex" class="size-4 text-emerald-600" />
                <span v-else class="tabular-nums">{{ index + 1 }}</span>
                {{ t(`plan.steps.${name}`) }}
            </li>
        </ol>

        <template v-if="step === 'days'">
            <p class="text-sm text-stone-600">{{ t('plan.daysHint') }}</p>
            <div class="flex items-center justify-between gap-2">
                <AppButton size="icon" :aria-label="t('plan.previousWeek')" @click="$emit('shiftWeek', -1)">
                    <ChevronLeft class="size-4" />
                </AppButton>
                <p class="text-sm font-medium">
                    {{ formatDay(period.from, locale) }} – {{ formatDay(period.to, locale) }}
                </p>
                <AppButton size="icon" :aria-label="t('plan.nextWeek')" @click="$emit('shiftWeek', 1)">
                    <ChevronRight class="size-4" />
                </AppButton>
            </div>
            <PlanMatrix v-model="slots" :days="days" :planned="planned" />
            <NumberField v-model="servings" :label="t('recipes.servings')" :min="1" :max="50" steppers />
        </template>

        <PlanPreferencesForm
            v-else-if="step === 'preferences'"
            v-model:preferences="preferences"
            v-model:courses="courses"
            v-model:tag-ids="tagIds"
        />

        <template v-else>
            <PlanPreview
                v-model="rows"
                :candidate-count="candidateCount"
                :busy="suggest.isPending.value"
                @reroll="(row) => suggest.mutate(pinsForReroll(rows, row))"
                @reroll-all="suggest.mutate(pinsForRerollAll(rows))"
            />
            <CheckboxField v-model="createList" :label="t('plan.thenCreateList')" />
        </template>

        <template #actions>
            <AppButton v-if="stepIndex > 0" @click="step = STEPS[stepIndex - 1] ?? 'days'">{{
                t('common.back')
            }}</AppButton>
            <AppButton
                v-if="step === 'days'"
                variant="primary"
                :disabled="slots.length === 0"
                @click="step = 'preferences'"
            >
                {{ t('plan.nextWithCount', { count: slots.length }) }}
            </AppButton>
            <AppButton
                v-else-if="step === 'preferences'"
                variant="primary"
                :disabled="suggest.isPending.value"
                @click="suggest.mutate(pinsForRerollAll(rows))"
            >
                {{ t('plan.suggest') }}
            </AppButton>
            <AppButton
                v-else
                variant="primary"
                :disabled="rows.length === 0 || save.isPending.value"
                @click="save.mutate()"
            >
                {{ t('plan.saveCount', { count: rows.length }) }}
            </AppButton>
        </template>
    </DialogPanel>
</template>
