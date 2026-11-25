<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { shoppingApi } from '@/api/planning';
import type { ShoppingListItem } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import ShoppingAddField from '@/components/ShoppingAddField.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';

/**
 * Notes what was bought in place of a position: a product, or anything
 * typed. The position counts as done, while its own product is neither
 * bought nor stocked.
 */
const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{ item: ShoppingListItem; name: string }>();

const { t } = useI18n();
const { showError } = useToasts();
const queryClient = useQueryClient();

const save = useMutation({
    mutationFn: (substitute: { substitute_product_id: number } | { substitute_label: string }) =>
        shoppingApi.substitute(props.item.id, substitute),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
        open.value = false;
    },
    onError: showError,
});
</script>

<template>
    <DialogPanel
        v-model:open="open"
        :title="t('shopping.substituteTitle')"
        :description="t('shopping.substituteDescription', { name })"
        :close-label="t('common.close')"
    >
        <ShoppingAddField
            @product="(product) => save.mutate({ substitute_product_id: product.id })"
            @text="(label) => save.mutate({ substitute_label: label })"
        />
    </DialogPanel>
</template>
