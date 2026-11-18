<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { CalendarPlus, ExternalLink, Heart, Pencil, Trash2 } from 'lucide-vue-next';
import { recipesApi } from '@/api/recipes';
import type { Ingredient, Step } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import { toIsoDate } from '@/lib/dates';
import { formatNumber, formatQuantity } from '@/lib/format';
import PlanEntryDialog from '@/components/PlanEntryDialog.vue';
import { useHouseholdSettings } from '@/composables/useHouseholdSettings';
import RatingControl from '@/components/RatingControl.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import NumberField from '@/components/ui/NumberField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

/**
 * A recipe with ingredients scaled to the chosen number of servings, the
 * member's rating and favorite mark. Narrow screens switch between
 * ingredients and method, wide ones show both.
 */
const props = defineProps<{ id: number }>();

const { t, locale } = useI18n();
const router = useRouter();
const queryClient = useQueryClient();
const { showError } = useToasts();

const recipe = useQuery({ queryKey: ['recipe', () => props.id], queryFn: () => recipesApi.get(props.id) });

const TAB_CLASS =
    'cursor-pointer rounded-lg px-4 py-2 text-sm font-medium text-stone-600 data-[state=active]:bg-white data-[state=active]:text-stone-900 data-[state=active]:shadow-sm';

const { defaultServings } = useHouseholdSettings();

const view = ref<'ingredients' | 'method'>('ingredients');
const servings = ref<number | null>(null);
const isPlanning = ref(false);
const isDeleting = ref(false);
const today = toIsoDate(new Date());

watch(
    () => [recipe.data.value, defaultServings.value] as const,
    ([loaded]) => {
        servings.value = loaded?.servings ? defaultServings.value : null;
    },
    { immediate: true },
);

const scale = computed(() => {
    const original = recipe.data.value?.servings;

    return original && servings.value ? servings.value / original : 1;
});

const sections = computed(() => {
    const groups: { title: string | null; ingredients: Ingredient[] }[] = [];

    for (const ingredient of recipe.data.value?.ingredients ?? []) {
        const last = groups.at(-1);

        if (last && last.title === ingredient.section) {
            last.ingredients.push(ingredient);
        } else {
            groups.push({ title: ingredient.section, ingredients: [ingredient] });
        }
    }

    return groups;
});

const stepGroups = computed(() => {
    const groups: { title: string | null; steps: Step[] }[] = [];

    for (const step of recipe.data.value?.steps ?? []) {
        const last = groups.at(-1);

        if (last && last.title === step.section) {
            last.steps.push(step);
        } else {
            groups.push({ title: step.section, steps: [step] });
        }
    }

    return groups;
});

const tagNames = computed(() => [...new Set((recipe.data.value?.tags ?? []).map((tag) => tag.name))]);

/**
 * Note and estimate marker of an ingredient as one remark after its name.
 */
function remarkOf(ingredient: Ingredient): string {
    const remarks = [ingredient.note, ingredient.is_estimated ? t('recipes.estimated') : null].filter(
        Boolean,
    );

    return remarks.length > 0 ? `(${remarks.join(', ')})` : '';
}

/**
 * Store a changed mark and take over the recipe the server answers with.
 */
const mark = useMutation({
    mutationFn: (change: { rating: number | null } | { favorite: boolean }) =>
        'rating' in change
            ? recipesApi.rate(props.id, change.rating)
            : recipesApi.setFavorite(props.id, change.favorite),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['recipe'] });
        queryClient.invalidateQueries({ queryKey: ['recipes'] });
    },
    onError: showError,
});

const rating = computed({
    get: () => recipe.data.value?.rating ?? null,
    set: (value: number | null) => mark.mutate({ rating: value }),
});

const isFavorite = computed({
    get: () => recipe.data.value?.is_favorite ?? false,
    set: (value: boolean) => mark.mutate({ favorite: value }),
});

const remove = useMutation({
    mutationFn: () => recipesApi.remove(props.id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['recipes'] });
        router.push({ name: 'recipes' });
    },
    onError: showError,
});
</script>

<template>
    <article v-if="recipe.data.value" class="space-y-8">
        <header class="grid gap-6" :class="{ 'md:grid-cols-[18rem_1fr]': recipe.data.value.cover_url }">
            <img
                v-if="recipe.data.value.cover_url"
                :src="recipe.data.value.cover_url"
                :alt="recipe.data.value.title"
                class="aspect-[4/3] w-full rounded-2xl object-cover"
            />
            <div class="space-y-3">
                <h1 class="text-2xl font-semibold text-stone-900">{{ recipe.data.value.title }}</h1>
                <p v-if="recipe.data.value.description" class="text-stone-600">
                    {{ recipe.data.value.description }}
                </p>
                <p class="flex flex-wrap gap-2">
                    <StatusBadge v-if="recipe.data.value.course_name" tone="success">
                        {{ recipe.data.value.course_name }}
                    </StatusBadge>
                    <StatusBadge v-for="name in tagNames" :key="name">{{ name }}</StatusBadge>
                </p>
                <div class="space-y-1">
                    <p class="text-sm font-medium text-stone-700">{{ t('recipes.rating') }}</p>
                    <RatingControl v-model="rating" :label="t('recipes.rating')">
                        <span
                            v-if="recipe.data.value.average_rating != null"
                            class="ml-auto text-sm text-stone-500 tabular-nums"
                            :title="
                                t('recipes.averageRating', {
                                    average: formatNumber(recipe.data.value.average_rating / 2, locale, 1),
                                    count: recipe.data.value.rating_count,
                                })
                            "
                        >
                            Ø {{ formatNumber(recipe.data.value.average_rating / 2, locale, 1) }} ({{
                                recipe.data.value.rating_count
                            }})
                        </span>
                    </RatingControl>
                </div>
                <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-stone-600">
                    <div v-if="recipe.data.value.prep_minutes" class="flex gap-1">
                        <dt>{{ t('recipes.prep') }}:</dt>
                        <dd>{{ t('recipes.minutes', { count: recipe.data.value.prep_minutes }) }}</dd>
                    </div>
                    <div v-if="recipe.data.value.cook_minutes" class="flex gap-1">
                        <dt>{{ t('recipes.cook') }}:</dt>
                        <dd>{{ t('recipes.minutes', { count: recipe.data.value.cook_minutes }) }}</dd>
                    </div>
                    <div v-if="recipe.data.value.yield_text" class="flex gap-1">
                        <dt>{{ t('recipes.yield') }}:</dt>
                        <dd>{{ recipe.data.value.yield_text }}</dd>
                    </div>
                </dl>
                <div class="flex flex-wrap gap-2">
                    <AppButton variant="primary" @click="isPlanning = true">
                        <CalendarPlus class="size-4" />{{ t('plan.addEntry') }}
                    </AppButton>
                    <AppButton :aria-pressed="isFavorite" @click="isFavorite = !isFavorite">
                        <Heart class="size-4" :class="{ 'fill-rose-500 text-rose-500': isFavorite }" />
                        {{ t('recipes.favorite') }}
                    </AppButton>
                    <RouterLink v-slot="{ navigate }" :to="{ name: 'recipe-edit', params: { id } }" custom>
                        <AppButton @click="navigate"
                            ><Pencil class="size-4" />{{ t('common.edit') }}</AppButton
                        >
                    </RouterLink>
                    <AppButton variant="ghost" @click="isDeleting = true"
                        ><Trash2 class="size-4" />{{ t('common.delete') }}</AppButton
                    >
                    <a
                        v-if="recipe.data.value.source_url"
                        :href="recipe.data.value.source_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-10 items-center gap-2 px-2 text-sm text-emerald-700 hover:underline"
                    >
                        <ExternalLink class="size-4" />{{ t('recipes.source') }}
                    </a>
                </div>
            </div>
        </header>

        <TabsRoot v-model="view" class="md:hidden">
            <TabsList class="grid grid-cols-2 gap-1 rounded-xl bg-stone-200/70 p-1">
                <TabsTrigger value="ingredients" :class="TAB_CLASS">{{
                    t('recipes.ingredients')
                }}</TabsTrigger>
                <TabsTrigger value="method" :class="TAB_CLASS">{{ t('recipes.steps') }}</TabsTrigger>
            </TabsList>
        </TabsRoot>

        <div class="grid gap-8 md:grid-cols-[18rem_1fr]">
            <section :class="{ 'hidden md:block': view !== 'ingredients' }">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">{{ t('recipes.ingredients') }}</h2>
                    <NumberField
                        v-if="recipe.data.value.servings"
                        v-model="servings"
                        class="w-28"
                        :min="1"
                        :max="100"
                        steppers
                        :aria-label="t('recipes.servings')"
                    />
                </div>
                <div v-for="(section, index) in sections" :key="index" class="mb-4">
                    <h3 v-if="section.title" class="mb-1 text-sm font-semibold text-stone-700">
                        {{ section.title }}
                    </h3>
                    <ul class="divide-y divide-stone-100 text-sm">
                        <li
                            v-for="ingredient in section.ingredients"
                            :key="ingredient.id"
                            class="flex gap-3 py-1.5"
                        >
                            <span class="w-20 shrink-0 text-right font-medium tabular-nums">
                                {{ formatQuantity(ingredient, scale, locale) }}
                            </span>
                            <span>
                                {{ ingredient.product?.name ?? ingredient.raw_text }}
                                <span class="text-stone-500">{{ remarkOf(ingredient) }}</span>
                            </span>
                        </li>
                    </ul>
                </div>
                <template v-if="recipe.data.value.tools.length > 0">
                    <h2 class="mt-6 mb-2 text-lg font-semibold">{{ t('recipes.tools') }}</h2>
                    <ul class="flex flex-wrap gap-2 text-sm">
                        <li v-for="tool in recipe.data.value.tools" :key="tool.id">
                            <StatusBadge :tone="tool.is_owned ? 'neutral' : 'warning'">
                                {{ tool.is_owned ? tool.name : t('tools.missing', { name: tool.name }) }}
                            </StatusBadge>
                        </li>
                    </ul>
                </template>
            </section>

            <section :class="{ 'hidden md:block': view !== 'method' }">
                <h2 class="mb-3 text-lg font-semibold">{{ t('recipes.steps') }}</h2>
                <div v-for="(group, index) in stepGroups" :key="index" class="mb-6">
                    <h3 v-if="group.title" class="mb-2 font-semibold text-stone-800">{{ group.title }}</h3>
                    <ol class="space-y-4">
                        <li v-for="step in group.steps" :key="step.id" class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-800"
                            >
                                {{ step.position }}
                            </span>
                            <p class="text-stone-800">{{ step.text }}</p>
                        </li>
                    </ol>
                </div>
            </section>
        </div>

        <PlanEntryDialog v-model:open="isPlanning" :date="today" :recipe="recipe.data.value" />
        <ConfirmDialog
            v-model:open="isDeleting"
            :title="t('recipes.deleteTitle')"
            :description="t('recipes.deleteDescription', { title: recipe.data.value.title })"
            :confirm-label="t('common.delete')"
            :cancel-label="t('common.cancel')"
            @confirm="remove.mutate()"
        />
    </article>
</template>
