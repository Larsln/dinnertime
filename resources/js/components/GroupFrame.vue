<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Plus, Trash2 } from 'lucide-vue-next';
import AppButton from '@/components/ui/AppButton.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Frame around one group of the recipe editor: its freely chosen title,
 * the rows inside and the buttons to add a row or remove the group.
 */
const title = defineModel<string>('title', { required: true });

defineProps<{ addLabel: string; removable: boolean }>();

defineEmits<{ add: []; remove: [] }>();

const { t } = useI18n();
</script>

<template>
    <div class="rounded-tile border border-line p-3">
        <div class="mb-2 flex items-center gap-2">
            <TextField v-model="title" class="flex-1" :placeholder="t('recipes.groupTitle')" />
            <AppButton
                v-if="removable"
                size="icon"
                variant="ghost"
                :aria-label="t('recipes.removeGroup')"
                @click="$emit('remove')"
            >
                <Trash2 class="size-4" />
            </AppButton>
        </div>
        <slot />
        <AppButton class="mt-2" size="sm" @click="$emit('add')"
            ><Plus class="size-4" />{{ addLabel }}</AppButton
        >
    </div>
</template>
