<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useQueryClient } from '@tanstack/vue-query';
import { toolsApi } from '@/api/tools';
import type { Tool } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import ComboboxField from '@/components/ui/ComboboxField.vue';

/**
 * Finds a tool in the catalog. With `creatable` set, an unknown name can
 * be added as a new tool.
 */
const model = defineModel<Tool | null>({ required: true });

withDefaults(defineProps<{ label?: string; creatable?: boolean }>(), { label: undefined, creatable: false });

const { t } = useI18n();
const { showError } = useToasts();
const queryClient = useQueryClient();

async function create(name: string): Promise<void> {
    try {
        model.value = await toolsApi.create(name);
        queryClient.invalidateQueries({ queryKey: ['tools'] });
    } catch (error) {
        showError(error);
    }
}
</script>

<template>
    <ComboboxField
        v-model="model"
        :search="toolsApi.list"
        :display="(tool) => tool.name"
        :label="label"
        :placeholder="t('tools.searchPlaceholder')"
        :empty-text="creatable ? undefined : t('common.noResults')"
        :create-label="creatable ? (name) => t('tools.create', { name }) : undefined"
        @create="create"
    />
</template>
