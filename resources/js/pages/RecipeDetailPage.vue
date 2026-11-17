<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { CalendarPlus, ExternalLink, Pencil, Trash2 } from 'lucide-vue-next';
import { recipesApi } from '@/api/recipes';
import type { Ingredient } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import { toIsoDate } from '@/lib/dates';
import { formatQuantity } from '@/lib/format';
import PlanEntryDialog from '@/components/PlanEntryDialog.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import NumberField from '@/components/ui/NumberField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

/**
 * A recipe with ingredients scaled to the chosen number of servings.
 */
const props = defineProps<{ id: number }>();

const { t, locale } = useI18n();
const router = useRouter();
const queryClient = useQueryClient();
const { showError } = useToasts();

const recipe = useQuery({ queryKey: ['recipe', () => props.id], queryFn: () => recipesApi.get(props.id) });

const servings = ref<number | null>(null);
const isPlanning = ref(false);
const isDeleting = ref(false);
const today = toIsoDate(new Date());

watch(
    () => recipe.data.value,
    (loaded) => {
        servings.value = loaded?.servings ?? null;
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
                    <StatusBadge v-for="name in tagNames" :key="name">{{ name }}</StatusBadge>
                </p>
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

        <div class="grid gap-8 md:grid-cols-[18rem_1fr]">
            <section>
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
                    <p class="text-sm text-stone-700">
                        {{ recipe.data.value.tools.map((tool) => tool.name).join(', ') }}
                    </p>
                </template>
            </section>

            <section>
                <h2 class="mb-3 text-lg font-semibold">{{ t('recipes.steps') }}</h2>
                <ol class="space-y-4">
                    <li v-for="step in recipe.data.value.steps" :key="step.id">
                        <h3 v-if="step.title" class="mb-1 text-sm font-semibold text-stone-700">
                            {{ step.title }}
                        </h3>
                        <div class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-800"
                            >
                                {{ step.position }}
                            </span>
                            <p class="text-stone-800">{{ step.text }}</p>
                        </div>
                    </li>
                </ol>
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
