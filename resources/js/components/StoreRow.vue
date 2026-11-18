<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { Check, Trash2 } from 'lucide-vue-next';
import { storesApi } from '@/api/stores';
import type { Store } from '@/api/types';
import { STORES_KEY } from '@/composables/useStores';
import { useToasts } from '@/composables/useToasts';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Line of the store list: rename the store, state whether the household
 * buys there at the moment, or delete it.
 */
const props = defineProps<{ store: Store }>();

const { t } = useI18n();
const queryClient = useQueryClient();
const { showError } = useToasts();

const name = ref(props.store.name);
const isDeleting = ref(false);

watch(
    () => props.store.name,
    (value) => {
        name.value = value;
    },
);

function refresh(): void {
    for (const key of [STORES_KEY, ['products'], ['review'], ['shopping-list']]) {
        queryClient.invalidateQueries({ queryKey: key });
    }
}

const update = useMutation({
    mutationFn: (changes: { name?: string; is_active?: boolean }) =>
        storesApi.update(props.store.id, changes),
    onSuccess: refresh,
    onError: showError,
});

const remove = useMutation({
    mutationFn: () => storesApi.remove(props.store.id),
    onSuccess: refresh,
    onError: showError,
});
</script>

<template>
    <li class="flex flex-wrap items-center gap-3 px-4 py-3">
        <TextField v-model="name" class="min-w-40 flex-1" />
        <AppButton
            v-if="name.trim() !== store.name && name.trim() !== ''"
            size="icon"
            :aria-label="t('common.save')"
            @click="update.mutate({ name: name.trim() })"
        >
            <Check class="size-4" />
        </AppButton>
        <span class="text-sm text-stone-500">{{
            t('stores.offerCount', { count: store.offer_count ?? 0 })
        }}</span>
        <CheckboxField
            :model-value="store.is_active"
            :label="t('stores.active')"
            @update:model-value="(active) => update.mutate({ is_active: active })"
        />
        <AppButton size="icon" variant="ghost" :aria-label="t('common.delete')" @click="isDeleting = true">
            <Trash2 class="size-4" />
        </AppButton>

        <ConfirmDialog
            v-model:open="isDeleting"
            :title="t('stores.deleteTitle')"
            :description="t('stores.deleteDescription', { name: store.name })"
            :confirm-label="t('common.delete')"
            :cancel-label="t('common.cancel')"
            @confirm="remove.mutate()"
        />
    </li>
</template>
