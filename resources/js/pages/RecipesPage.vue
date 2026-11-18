<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { Plus } from 'lucide-vue-next';
import { recipesApi } from '@/api/recipes';
import type { CourseKey } from '@/api/types';
import { useDebounced } from '@/composables/useDebounced';
import { useReferenceData } from '@/composables/useReferenceData';
import ImportPanel from '@/components/ImportPanel.vue';
import RecipeCard from '@/components/RecipeCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SelectField from '@/components/ui/SelectField.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * All recipes with search, filters for course, tag and favorites, and the link import.
 */
const { t } = useI18n();
const { tags, courses } = useReferenceData();

const search = ref('');
const tagId = ref<number | null>(null);
const course = ref<CourseKey | null>(null);
const favoritesOnly = ref(false);
const debouncedSearch = useDebounced(search);

const tagOptions = computed(() =>
    tags.value.map((tag) => ({ value: tag.id, label: `${tag.name} · ${t(`tagTypes.${tag.type}`)}` })),
);

const courseOptions = computed(() => courses.value.map((entry) => ({ value: entry.key, label: entry.name })));

const recipes = useQuery({
    queryKey: ['recipes', debouncedSearch, tagId, course, favoritesOnly],
    queryFn: () =>
        recipesApi.list({
            search: debouncedSearch.value,
            tag: tagId.value,
            course: course.value,
            favorite: favoritesOnly.value ? 1 : null,
        }),
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

    <div class="mb-4 grid items-center gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_14rem_14rem_auto]">
        <TextField v-model="search" type="search" :placeholder="t('recipes.searchPlaceholder')" />
        <SelectField
            v-model="course"
            :options="courseOptions"
            :placeholder="t('recipes.allCourses')"
            :empty-label="t('recipes.allCourses')"
        />
        <SelectField
            v-model="tagId"
            :options="tagOptions"
            :placeholder="t('recipes.allTags')"
            :empty-label="t('recipes.allTags')"
        />
        <CheckboxField v-model="favoritesOnly" :label="t('recipes.favoritesOnly')" />
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
