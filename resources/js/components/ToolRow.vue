<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { Check, Merge, Trash2 } from 'lucide-vue-next';
import { toolsApi } from '@/api/tools';
import type { Tool } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import ToolPicker from '@/components/ToolPicker.vue';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Line of the tool catalog: rename the tool, state whether the household
 * owns it, merge it into another one or delete it.
 */
const props = defineProps<{ tool: Tool }>();

const { t } = useI18n();
const queryClient = useQueryClient();
const { showError } = useToasts();

const name = ref(props.tool.name);
const isMerging = ref(false);
const isDeleting = ref(false);
const mergeTarget = ref<Tool | null>(null);

watch(
    () => props.tool.name,
    (value) => {
        name.value = value;
    },
);

function refresh(): void {
    queryClient.invalidateQueries({ queryKey: ['tools'] });
    queryClient.invalidateQueries({ queryKey: ['recipe'] });
}

const update = useMutation({
    mutationFn: (changes: { name?: string; is_owned?: boolean }) => toolsApi.update(props.tool.id, changes),
    onSuccess: refresh,
    onError: showError,
});

const merge = useMutation({
    mutationFn: () => toolsApi.merge(props.tool.id, mergeTarget.value?.id ?? 0),
    onSuccess: () => {
        isMerging.value = false;
        refresh();
    },
    onError: showError,
});

const remove = useMutation({
    mutationFn: () => toolsApi.remove(props.tool.id),
    onSuccess: refresh,
    onError: showError,
});
</script>

<template>
    <li class="flex flex-wrap items-center gap-3 px-4 py-3">
        <TextField v-model="name" class="min-w-40 flex-1" />
        <AppButton
            v-if="name.trim() !== tool.name && name.trim() !== ''"
            size="icon"
            :aria-label="t('common.save')"
            @click="update.mutate({ name: name.trim() })"
        >
            <Check class="size-4" />
        </AppButton>
        <span class="text-sm text-ink-muted">{{
            t('tools.recipeCount', { count: tool.recipe_count ?? 0 })
        }}</span>
        <CheckboxField
            :model-value="tool.is_owned"
            :label="t('tools.owned')"
            @update:model-value="(owned) => update.mutate({ is_owned: owned })"
        />
        <AppButton size="icon" variant="ghost" :aria-label="t('products.merge')" @click="isMerging = true">
            <Merge class="size-4" />
        </AppButton>
        <AppButton size="icon" variant="ghost" :aria-label="t('common.delete')" @click="isDeleting = true">
            <Trash2 class="size-4" />
        </AppButton>

        <DialogPanel
            v-model:open="isMerging"
            :title="t('products.merge')"
            :description="t('tools.mergeDescription', { name: tool.name })"
            :close-label="t('common.close')"
        >
            <ToolPicker v-model="mergeTarget" :label="t('products.mergeInto')" />
            <template #actions>
                <AppButton
                    variant="danger"
                    :disabled="mergeTarget === null || mergeTarget.id === tool.id || merge.isPending.value"
                    @click="merge.mutate()"
                >
                    {{ t('products.merge') }}
                </AppButton>
            </template>
        </DialogPanel>

        <ConfirmDialog
            v-model:open="isDeleting"
            :title="t('tools.deleteTitle')"
            :description="t('tools.deleteDescription', { name: tool.name })"
            :confirm-label="t('common.delete')"
            :cancel-label="t('common.cancel')"
            @confirm="remove.mutate()"
        />
    </li>
</template>
