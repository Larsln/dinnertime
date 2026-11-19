<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { Plus, SlidersHorizontal } from 'lucide-vue-next';
import { recipesApi } from '@/api/recipes';
import type { CourseKey } from '@/api/types';
import { useDebounced } from '@/composables/useDebounced';
import { useReferenceData } from '@/composables/useReferenceData';
import ImportPanel from '@/components/ImportPanel.vue';
import RecipeCard from '@/components/RecipeCard.vue';
import RecipeFilterDialog from '@/components/RecipeFilterDialog.vue';
import TodayPanel from '@/components/TodayPanel.vue';
import AppButton from '@/components/ui/AppButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import RemovableChip from '@/components/ui/RemovableChip.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * All recipes with search and filters for courses, meals, properties and
 * favorites, and the link import, below what is up next today. Active
 * filters are listed as chips that take them away again.
 */
const { t } = useI18n();
const { tags, courses } = useReferenceData();

const search = ref('');
const courseKeys = ref<CourseKey[]>([]);
const mealTagIds = ref<number[]>([]);
const otherTagIds = ref<number[]>([]);
const favoritesOnly = ref(false);
const isFiltering = ref(false);
const debouncedSearch = useDebounced(search);

const tagIds = computed(() => [...mealTagIds.value, ...otherTagIds.value]);

const activeFilters = computed(() => [
    ...courses.value
        .filter((entry) => courseKeys.value.includes(entry.key))
        .map((entry) => ({
            key: `course-${entry.key}`,
            label: entry.name,
            remove: () => {
                courseKeys.value = courseKeys.value.filter((key) => key !== entry.key);
            },
        })),
    ...tags.value
        .filter((tag) => tagIds.value.includes(tag.id))
        .map((tag) => ({
            key: `tag-${tag.id}`,
            label: tag.name,
            remove: () => {
                mealTagIds.value = mealTagIds.value.filter((id) => id !== tag.id);
                otherTagIds.value = otherTagIds.value.filter((id) => id !== tag.id);
            },
        })),
    ...(favoritesOnly.value
        ? [
              {
                  key: 'favorite',
                  label: t('recipes.favoritesOnly'),
                  remove: () => {
                      favoritesOnly.value = false;
                  },
              },
          ]
        : []),
]);

const isNarrowed = computed(() => search.value.trim() !== '' || activeFilters.value.length > 0);

const recipes = useQuery({
    queryKey: ['recipes', debouncedSearch, courseKeys, tagIds, favoritesOnly],
    queryFn: () =>
        recipesApi.list({
            search: debouncedSearch.value,
            tags: tagIds.value,
            courses: courseKeys.value,
            favorite: favoritesOnly.value ? 1 : null,
        }),
    placeholderData: keepPreviousData,
});

function resetFilters(): void {
    courseKeys.value = [];
    mealTagIds.value = [];
    otherTagIds.value = [];
    favoritesOnly.value = false;
}

function resetAll(): void {
    search.value = '';
    resetFilters();
}
</script>

<template>
    <PageHeader :title="t('nav.recipes')">
        <RouterLink v-slot="{ navigate }" :to="{ name: 'recipe-new' }" custom>
            <AppButton @click="navigate"><Plus class="size-4" />{{ t('recipes.new') }}</AppButton>
        </RouterLink>
    </PageHeader>

    <TodayPanel v-if="!isNarrowed" />

    <ImportPanel />

    <div class="mb-4 flex items-center gap-3">
        <TextField
            v-model="search"
            type="search"
            :placeholder="t('recipes.searchPlaceholder')"
            class="min-w-0 flex-1"
        />
        <AppButton @click="isFiltering = true">
            <SlidersHorizontal class="size-4" />{{ t('recipes.filter') }}
            <span
                v-if="activeFilters.length"
                class="-mr-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1.5 text-xs font-semibold text-on-accent"
                >{{ activeFilters.length }}</span
            >
        </AppButton>
    </div>

    <div v-if="activeFilters.length" class="mb-4 flex flex-wrap items-center gap-2">
        <RemovableChip
            v-for="filter in activeFilters"
            :key="filter.key"
            :label="filter.label"
            :remove-label="t('recipes.removeFilter', { name: filter.label })"
            @remove="filter.remove()"
        />
        <AppButton v-if="activeFilters.length > 1" variant="ghost" size="sm" @click="resetFilters">
            {{ t('recipes.clearFilters') }}
        </AppButton>
    </div>

    <div v-if="recipes.data.value?.data.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
        <RecipeCard v-for="recipe in recipes.data.value.data" :key="recipe.id" :recipe="recipe" />
    </div>
    <template v-else-if="!recipes.isPending.value">
        <EmptyState v-if="isNarrowed" :title="t('recipes.emptyFiltered')">
            <AppButton @click="resetAll">{{ t('recipes.resetFilters') }}</AppButton>
        </EmptyState>
        <EmptyState v-else :title="t('recipes.empty')" :hint="t('recipes.emptyHint')" />
    </template>

    <RecipeFilterDialog
        v-model:open="isFiltering"
        v-model:courses="courseKeys"
        v-model:meal-tags="mealTagIds"
        v-model:other-tags="otherTagIds"
        v-model:favorite="favoritesOnly"
        :can-reset="activeFilters.length > 0"
        @reset="resetFilters"
    />
</template>
