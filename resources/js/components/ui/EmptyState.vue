<script setup lang="ts">
/**
 * Placeholder shown where a list has no entries.
 */
defineProps<{ title: string; hint?: string }>();
</script>

<template>
    <div class="rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
        <p class="font-medium text-stone-800">{{ title }}</p>
        <p v-if="hint" class="mt-1 text-sm text-stone-500">{{ hint }}</p>
        <div v-if="$slots.default" class="mt-4 flex justify-center gap-2">
            <slot />
        </div>
    </div>
</template>
