<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { recipesApi } from '@/api/recipes';
import type { RecipeSummary } from '@/api/types';
import ComboboxField from '@/components/ui/ComboboxField.vue';

/**
 * Finds a recipe by its title.
 */
const model = defineModel<RecipeSummary | null>({ required: true });

defineProps<{ label?: string }>();

const { t } = useI18n();

async function search(term: string): Promise<RecipeSummary[]> {
    return (await recipesApi.list({ search: term })).data;
}
</script>

<template>
    <ComboboxField
        v-model="model"
        :search="search"
        :display="(recipe) => recipe.title"
        :label="label"
        :placeholder="t('recipes.searchPlaceholder')"
        :empty-text="t('common.noResults')"
    />
</template>
