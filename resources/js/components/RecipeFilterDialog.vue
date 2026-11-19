<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CourseKey, TagType } from '@/api/types';
import { useReferenceData } from '@/composables/useReferenceData';
import AppButton from '@/components/ui/AppButton.vue';
import ChipGroup from '@/components/ui/ChipGroup.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import SwitchField from '@/components/ui/SwitchField.vue';

/**
 * Filters of the recipe list: courses and meals, of which one has to
 * fit, properties, which all have to fit, and the favorites.
 */
const open = defineModel<boolean>('open', { required: true });
const courseKeys = defineModel<CourseKey[]>('courses', { required: true });
const mealTagIds = defineModel<number[]>('mealTags', { required: true });
const otherTagIds = defineModel<number[]>('otherTags', { required: true });
const favoritesOnly = defineModel<boolean>('favorite', { required: true });

defineProps<{ canReset: boolean }>();

defineEmits<{ reset: [] }>();

const { t } = useI18n();
const reference = useReferenceData();

const courseOptions = computed(() =>
    reference.courses.value.map((entry) => ({ value: entry.key, label: entry.name })),
);
const mealOptions = computed(() => tagOptions('meal'));
const otherOptions = computed(() => tagOptions('tag'));

function tagOptions(type: TagType): { value: number; label: string }[] {
    return reference.tags.value
        .filter((tag) => tag.type === type)
        .map((tag) => ({ value: tag.id, label: tag.name }));
}
</script>

<template>
    <DialogPanel v-model:open="open" :title="t('recipes.filter')" :close-label="t('common.close')" size="lg">
        <section v-if="courseOptions.length">
            <h3 class="text-sm font-semibold text-ink">{{ t('recipes.filterCourses') }}</h3>
            <p class="mb-2 text-xs text-ink-muted">{{ t('recipes.filterAny') }}</p>
            <ChipGroup v-model="courseKeys" :options="courseOptions" />
        </section>
        <section v-if="mealOptions.length">
            <h3 class="text-sm font-semibold text-ink">{{ t('recipes.filterMeals') }}</h3>
            <p class="mb-2 text-xs text-ink-muted">{{ t('recipes.filterAny') }}</p>
            <ChipGroup v-model="mealTagIds" :options="mealOptions" />
        </section>
        <section v-if="otherOptions.length">
            <h3 class="text-sm font-semibold text-ink">{{ t('recipes.filterTags') }}</h3>
            <p class="mb-2 text-xs text-ink-muted">{{ t('recipes.filterAll') }}</p>
            <ChipGroup v-model="otherTagIds" :options="otherOptions" />
        </section>
        <SwitchField v-model="favoritesOnly" :label="t('recipes.favoritesOnly')" />
        <template #actions>
            <AppButton variant="ghost" :disabled="!canReset" @click="$emit('reset')">
                {{ t('recipes.filterReset') }}
            </AppButton>
            <AppButton variant="primary" @click="open = false">{{ t('common.done') }}</AppButton>
        </template>
    </DialogPanel>
</template>
