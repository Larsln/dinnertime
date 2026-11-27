<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import { recipesApi } from '@/api/recipes';
import type { RecipeSummary } from '@/api/types';
import ChipGroup from '@/components/ui/ChipGroup.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';

/**
 * Chooses the sides served with a dish. Only recipes of the kinds the
 * dish is served with are offered, those served with it last come first.
 */
const sideIds = defineModel<number[]>({ required: true });

const props = defineProps<{ recipe: RecipeSummary }>();

const { t } = useI18n();

const sides = useQuery({
    queryKey: ['recipes', 'sides', () => props.recipe.id],
    queryFn: () => recipesApi.sides(props.recipe.id),
});

const options = computed(() =>
    (sides.data.value ?? []).map((side) => ({ value: side.id, label: side.title })),
);
</script>

<template>
    <div>
        <FieldLabel>{{ t('plan.sides.label') }}</FieldLabel>
        <ChipGroup v-if="options.length > 0" v-model="sideIds" :options="options" />
        <p v-else-if="sides.isSuccess.value" class="text-sm text-ink-muted">{{ t('plan.sides.none') }}</p>
    </div>
</template>
