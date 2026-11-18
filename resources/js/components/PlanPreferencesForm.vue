<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CourseKey } from '@/api/types';
import { useReferenceData } from '@/composables/useReferenceData';
import { PREFERENCE_KEYS, type PlanPreferences } from '@/lib/planWizard';
import ChipGroup from '@/components/ui/ChipGroup.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import SwitchField from '@/components/ui/SwitchField.vue';

/**
 * Second step of planning a week: what the suggestion should favor and
 * which courses and tags it may draw from.
 */
const preferences = defineModel<PlanPreferences>('preferences', { required: true });
const courses = defineModel<CourseKey[]>('courses', { required: true });
const tagIds = defineModel<number[]>('tagIds', { required: true });

const { t } = useI18n();
const reference = useReferenceData();

const courseOptions = computed(() =>
    reference.courses.value.map((course) => ({ value: course.key, label: course.name })),
);
const tagOptions = computed(() =>
    reference.tags.value
        .filter((tag) => tag.type === 'tag')
        .map((tag) => ({ value: tag.id, label: tag.name })),
);
</script>

<template>
    <div class="space-y-4">
        <div class="divide-y divide-stone-100 rounded-xl border border-stone-200 px-3">
            <SwitchField
                v-for="key in PREFERENCE_KEYS"
                :key="key"
                v-model="preferences[key]"
                :label="t(`plan.preferences.${key}.label`)"
                :hint="t(`plan.preferences.${key}.hint`)"
            />
        </div>
        <div>
            <FieldLabel>{{ t('plan.onlyCourses') }}</FieldLabel>
            <ChipGroup v-model="courses" :options="courseOptions" />
        </div>
        <div>
            <FieldLabel>{{ t('plan.onlyTags') }}</FieldLabel>
            <ChipGroup v-model="tagIds" :options="tagOptions" />
        </div>
    </div>
</template>
