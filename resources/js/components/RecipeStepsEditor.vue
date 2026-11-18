<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { FolderPlus, Trash2 } from 'lucide-vue-next';
import { emptyGroup, emptyStepRow, type EditGroup, type StepRow } from '@/lib/recipeGroups';
import GroupFrame from '@/components/GroupFrame.vue';
import AppButton from '@/components/ui/AppButton.vue';
import TextArea from '@/components/ui/TextArea.vue';

/**
 * Preparation steps of a recipe, arranged in freely named groups such as
 * base, filling and decoration. Steps are numbered across all groups.
 */
const groups = defineModel<EditGroup<StepRow>[]>({ required: true });

const { t } = useI18n();

function numberOf(groupIndex: number, index: number): number {
    const before = groups.value.slice(0, groupIndex).reduce((count, group) => count + group.rows.length, 0);

    return before + index + 1;
}
</script>

<template>
    <div class="space-y-3">
        <GroupFrame
            v-for="(group, groupIndex) in groups"
            :key="group.key"
            v-model:title="group.title"
            :add-label="t('recipes.addStep')"
            :removable="groups.length > 1"
            @add="group.rows.push(emptyStepRow())"
            @remove="groups.splice(groupIndex, 1)"
        >
            <div v-for="(row, index) in group.rows" :key="row.key" class="flex gap-3 py-2">
                <span
                    class="mt-2 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-800"
                >
                    {{ numberOf(groupIndex, index) }}
                </span>
                <TextArea v-model="row.text" class="flex-1" :placeholder="t('recipes.stepText')" />
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
        <AppButton @click="groups.push(emptyGroup(emptyStepRow()))">
            <FolderPlus class="size-4" />{{ t('recipes.addGroup') }}
        </AppButton>
    </div>
</template>
