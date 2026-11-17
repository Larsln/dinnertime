<script setup lang="ts">
import { useTemplateRef } from 'vue';
import AppButton from './AppButton.vue';

/**
 * Button that opens the file chooser and reports the chosen file.
 */
defineProps<{ accept: string }>();

const emit = defineEmits<{ select: [file: File] }>();

const input = useTemplateRef<HTMLInputElement>('input');

function onChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        emit('select', file);
    }
}
</script>

<template>
    <AppButton @click="input?.click()">
        <slot />
        <input ref="input" type="file" :accept="accept" class="hidden" @change="onChange" />
    </AppButton>
</template>
