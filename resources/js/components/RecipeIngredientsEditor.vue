<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { FolderPlus, Trash2 } from 'lucide-vue-next';
import { useReferenceData } from '@/composables/useReferenceData';
import { emptyGroup, emptyIngredientRow, type EditGroup, type IngredientRow } from '@/lib/recipeGroups';
import GroupFrame from '@/components/GroupFrame.vue';
import ProductPicker from '@/components/ProductPicker.vue';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import NumberField from '@/components/ui/NumberField.vue';
import SelectField from '@/components/ui/SelectField.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Ingredient lines of a recipe, arranged in freely named groups.
 */
const groups = defineModel<EditGroup<IngredientRow>[]>({ required: true });

const { t } = useI18n();
const { units } = useReferenceData();

const unitOptions = computed(() => units.value.map((unit) => ({ value: unit.id, label: unit.code })));
</script>

<template>
    <div class="space-y-3">
        <GroupFrame
            v-for="(group, groupIndex) in groups"
            :key="group.key"
            v-model:title="group.title"
            :add-label="t('recipes.addIngredient')"
            :removable="groups.length > 1"
            @add="group.rows.push(emptyIngredientRow())"
            @remove="groups.splice(groupIndex, 1)"
        >
            <div
                v-for="(row, index) in group.rows"
                :key="row.key"
                class="grid grid-cols-2 gap-2 border-b border-stone-100 py-2 last:border-b-0 md:grid-cols-[5rem_7rem_1fr_1fr_auto_auto] md:items-center"
            >
                <NumberField v-model="row.quantity" :step="0.5" :placeholder="t('recipes.quantity')" />
                <SelectField
                    v-model="row.unitId"
                    :options="unitOptions"
                    :placeholder="t('recipes.unit')"
                    :empty-label="t('recipes.noUnit')"
                />
                <div class="col-span-2 space-y-2 md:col-span-1">
                    <ProductPicker v-model="row.product" creatable />
                    <TextField
                        v-if="row.product === null"
                        v-model="row.rawText"
                        :placeholder="t('recipes.freeText')"
                    />
                </div>
                <TextField
                    v-model="row.note"
                    class="col-span-2 md:col-span-1"
                    :placeholder="t('recipes.note')"
                />
                <CheckboxField v-model="row.isEstimated" :label="t('recipes.estimated')" />
                <AppButton
                    size="icon"
                    variant="ghost"
                    :aria-label="t('common.delete')"
                    @click="group.rows.splice(index, 1)"
                >
                    <Trash2 class="size-4" />
                </AppButton>
            </div>
        </GroupFrame>
        <AppButton @click="groups.push(emptyGroup(emptyIngredientRow()))">
            <FolderPlus class="size-4" />{{ t('recipes.addGroup') }}
        </AppButton>
    </div>
</template>
