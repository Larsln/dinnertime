<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { Plus } from 'lucide-vue-next';
import { recipesApi } from '@/api/recipes';
import { useDebounced } from '@/composables/useDebounced';
import { useReferenceData } from '@/composables/useReferenceData';
import ImportPanel from '@/components/ImportPanel.vue';
import RecipeCard from '@/components/RecipeCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SelectField from '@/components/ui/SelectField.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * All recipes with search, tag filter and the link import.
 */
const { t } = useI18n();
const { tags } = useReferenceData();

const search = ref('');
const tagId = ref<number | null>(null);
const debouncedSearch = useDebounced(search);

const tagOptions = computed(() =>
    tags.value.map((tag) => ({ value: tag.id, label: `${tag.name} · ${t(`tagTypes.${tag.type}`)}` })),
);

const recipes = useQuery({
    queryKey: ['recipes', debouncedSearch, tagId],
    queryFn: () => recipesApi.list({ search: debouncedSearch.value, tag: tagId.value }),
    placeholderData: keepPreviousData,
});
</script>

<template>
    <PageHeader :title="t('nav.recipes')">
        <RouterLink v-slot="{ navigate }" :to="{ name: 'recipe-new' }" custom>
            <AppButton @click="navigate"><Plus class="size-4" />{{ t('recipes.new') }}</AppButton>
        </RouterLink>
    </PageHeader>

    <ImportPanel />

    <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_16rem]">
        <TextField v-model="search" type="search" :placeholder="t('recipes.searchPlaceholder')" />
        <SelectField
            v-model="tagId"
            :options="tagOptions"
            :placeholder="t('recipes.allTags')"
            :empty-label="t('recipes.allTags')"
        />
    </div>

    <div v-if="recipes.data.value?.data.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
        <RecipeCard v-for="recipe in recipes.data.value.data" :key="recipe.id" :recipe="recipe" />
    </div>
    <EmptyState
        v-else-if="!recipes.isPending.value"
        :title="t('recipes.empty')"
        :hint="t('recipes.emptyHint')"
    />
</template>
