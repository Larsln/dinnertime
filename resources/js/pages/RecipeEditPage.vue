<script setup lang="ts">
import { computed, reactive, ref, watch, type WritableComputedRef } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { ImageUp, X } from 'lucide-vue-next';
import { recipesApi } from '@/api/recipes';
import type { CourseKey, Recipe, RecipeInput, TagType, Tool } from '@/api/types';
import { useReferenceData } from '@/composables/useReferenceData';
import { useToasts } from '@/composables/useToasts';
import {
    emptyGroup,
    emptyIngredientRow,
    emptyStepRow,
    flattenGroups,
    groupBySection,
    newKey,
    type EditGroup,
    type IngredientRow,
    type StepRow,
} from '@/lib/recipeGroups';
import RecipeIngredientsEditor from '@/components/RecipeIngredientsEditor.vue';
import RecipeStepsEditor from '@/components/RecipeStepsEditor.vue';
import ToolPicker from '@/components/ToolPicker.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ChipGroup from '@/components/ui/ChipGroup.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import FileButton from '@/components/ui/FileButton.vue';
import NumberField from '@/components/ui/NumberField.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SelectField from '@/components/ui/SelectField.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Form for creating a recipe or editing an existing one.
 */
const props = defineProps<{ id?: number }>();

const TAG_TYPES: TagType[] = ['meal', 'tag'];

const { t } = useI18n();
const router = useRouter();
const queryClient = useQueryClient();
const { showError } = useToasts();
const { tags, courses } = useReferenceData();

const form = reactive({
    title: '',
    description: '',
    course: null as CourseKey | null,
    servings: null as number | null,
    yieldText: '',
    prepMinutes: null as number | null,
    cookMinutes: null as number | null,
    sourceUrl: '',
    tagIds: [] as number[],
});

const ingredientGroups = ref<EditGroup<IngredientRow>[]>([emptyGroup(emptyIngredientRow())]);
const stepGroups = ref<EditGroup<StepRow>[]>([emptyGroup(emptyStepRow())]);
const tools = ref<Tool[]>([]);
const pickedTool = ref<Tool | null>(null);
const cover = ref<File | null>(null);

watch(pickedTool, (tool) => {
    if (tool && !tools.value.some((entry) => entry.id === tool.id)) {
        tools.value.push(tool);
    }

    pickedTool.value = null;
});

const existing = useQuery({
    queryKey: ['recipe', () => props.id],
    queryFn: () => recipesApi.get(props.id ?? 0),
    enabled: () => props.id !== undefined,
});

const courseOptions = computed(() => courses.value.map((entry) => ({ value: entry.key, label: entry.name })));
function fill(recipe: Recipe): void {
    Object.assign(form, {
        title: recipe.title,
        description: recipe.description ?? '',
        course: recipe.course,
        servings: recipe.servings,
        yieldText: recipe.yield_text ?? '',
        prepMinutes: recipe.prep_minutes,
        cookMinutes: recipe.cook_minutes,
        sourceUrl: recipe.source_url ?? '',
        tagIds: (recipe.tags ?? []).map((tag) => tag.id),
    });

    if (recipe.ingredients.length > 0) {
        ingredientGroups.value = groupBySection(recipe.ingredients, (ingredient) => ({
            key: newKey(),
            product: ingredient.product,
            quantity: ingredient.quantity,
            unitId: ingredient.unit?.id ?? null,
            note: ingredient.note ?? '',
            isEstimated: ingredient.is_estimated,
            rawText: ingredient.raw_text ?? '',
        }));
    }

    if (recipe.steps.length > 0) {
        stepGroups.value = groupBySection(recipe.steps, (step) => ({ key: newKey(), text: step.text }));
    }

    tools.value = [...recipe.tools];
}

watch(
    () => existing.data.value,
    (recipe) => {
        if (recipe) {
            fill(recipe);
        }
    },
    { immediate: true },
);

/**
 * Two-way binding for the tags of one type within the shared list of ids.
 */
function tagModel(type: TagType): WritableComputedRef<number[]> {
    const idsOfType = () => tags.value.filter((tag) => tag.type === type).map((tag) => tag.id);

    return computed({
        get: () => form.tagIds.filter((id) => idsOfType().includes(id)),
        set: (selected) => {
            form.tagIds = [...form.tagIds.filter((id) => !idsOfType().includes(id)), ...selected];
        },
    });
}

const tagModels = Object.fromEntries(TAG_TYPES.map((type) => [type, tagModel(type)])) as Record<
    TagType,
    WritableComputedRef<number[]>
>;

function tagOptions(type: TagType): { value: number; label: string }[] {
    return tags.value.filter((tag) => tag.type === type).map((tag) => ({ value: tag.id, label: tag.name }));
}

function toInput(): RecipeInput {
    return {
        title: form.title,
        description: form.description || null,
        course: form.course,
        servings: form.servings,
        yield_text: form.yieldText || null,
        prep_minutes: form.prepMinutes,
        cook_minutes: form.cookMinutes,
        source_url: form.sourceUrl || null,
        tag_ids: form.tagIds,
        tool_ids: tools.value.map((tool) => tool.id),
        ingredients: flattenGroups(ingredientGroups.value, (row, section) =>
            row.product === null && row.rawText.trim() === ''
                ? null
                : {
                      section,
                      product_id: row.product?.id ?? null,
                      quantity: row.quantity,
                      unit_id: row.unitId,
                      note: row.note || null,
                      is_estimated: row.isEstimated,
                      raw_text: row.product === null ? row.rawText.trim() : null,
                  },
        ),
        steps: flattenGroups(stepGroups.value, (row, section) =>
            row.text.trim() === '' ? null : { section, text: row.text.trim() },
        ),
    };
}

const save = useMutation({
    mutationFn: async () => {
        const recipe =
            props.id === undefined
                ? await recipesApi.create(toInput())
                : await recipesApi.update(props.id, toInput());

        if (cover.value) {
            await recipesApi.uploadCover(recipe.id, cover.value);
        }

        return recipe;
    },
    onSuccess: (recipe) => {
        queryClient.invalidateQueries({ queryKey: ['recipes'] });
        queryClient.invalidateQueries({ queryKey: ['recipe'] });
        queryClient.invalidateQueries({ queryKey: ['review'] });
        router.push({ name: 'recipe', params: { id: recipe.id } });
    },
    onError: showError,
});
</script>

<template>
    <form class="space-y-8" @submit.prevent="save.mutate()">
        <PageHeader :title="props.id === undefined ? t('recipes.new') : t('recipes.edit')">
            <AppButton @click="router.back()">{{ t('common.cancel') }}</AppButton>
            <AppButton type="submit" variant="primary" :disabled="save.isPending.value">{{
                t('common.save')
            }}</AppButton>
        </PageHeader>

        <section class="grid gap-4 rounded-card border border-line bg-surface p-4 sm:grid-cols-2">
            <TextField v-model="form.title" class="sm:col-span-2" :label="t('recipes.title')" required />
            <TextArea
                v-model="form.description"
                class="sm:col-span-2"
                :label="t('recipes.description')"
                :rows="2"
            />
            <SelectField
                v-model="form.course"
                :options="courseOptions"
                :label="t('recipes.course')"
                :placeholder="t('recipes.noCourse')"
                :empty-label="t('recipes.noCourse')"
            />
            <NumberField v-model="form.servings" :label="t('recipes.servings')" :min="1" :max="100" />
            <TextField
                v-model="form.yieldText"
                :label="t('recipes.yieldField')"
                :placeholder="t('recipes.yieldPlaceholder')"
            />
            <NumberField v-model="form.prepMinutes" :label="t('recipes.prepTime')" />
            <NumberField v-model="form.cookMinutes" :label="t('recipes.cookTime')" />
            <TextField v-model="form.sourceUrl" type="url" :label="t('recipes.source')" />
            <div>
                <ToolPicker v-model="pickedTool" :label="t('recipes.tools')" creatable />
                <ul class="mt-2 flex flex-wrap gap-2">
                    <li
                        v-for="(tool, index) in tools"
                        :key="tool.id"
                        class="inline-flex items-center gap-1 rounded-full bg-sunken py-0.5 pr-1 pl-3 text-sm"
                    >
                        {{ tool.name }}
                        <AppButton
                            size="icon"
                            variant="ghost"
                            class="size-6!"
                            :aria-label="t('common.delete')"
                            @click="tools.splice(index, 1)"
                        >
                            <X class="size-3.5" />
                        </AppButton>
                    </li>
                </ul>
            </div>
            <div class="sm:col-span-2">
                <FileButton accept="image/*" @select="(file) => (cover = file)">
                    <ImageUp class="size-4" />{{ cover?.name ?? t('recipes.chooseCover') }}
                </FileButton>
            </div>
        </section>

        <section class="space-y-4 rounded-card border border-line bg-surface p-4">
            <div v-for="type in TAG_TYPES" :key="type">
                <FieldLabel>{{ t(`tagTypes.${type}`) }}</FieldLabel>
                <ChipGroup v-model="tagModels[type].value" :options="tagOptions(type)" />
            </div>
        </section>

        <section class="rounded-card border border-line bg-surface p-4">
            <h2 class="mb-3 text-lg font-display font-bold">{{ t('recipes.ingredients') }}</h2>
            <RecipeIngredientsEditor v-model="ingredientGroups" />
        </section>

        <section class="rounded-card border border-line bg-surface p-4">
            <h2 class="mb-1 text-lg font-display font-bold">{{ t('recipes.steps') }}</h2>
            <p class="mb-3 text-sm text-ink-muted">
                {{ t('recipes.groupsHint') }} {{ t('recipes.amountsHint') }}
            </p>
            <RecipeStepsEditor v-model="stepGroups" />
        </section>
    </form>
</template>
