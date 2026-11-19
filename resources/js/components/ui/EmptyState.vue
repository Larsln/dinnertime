<script setup lang="ts">
/**
 * Placeholder shown where a list has no entries.
 */
defineProps<{ title: string; hint?: string }>();
</script>

<template>
    <div class="rounded-card border border-dashed border-line-strong px-6 py-12 text-center">
        <p class="font-medium text-ink">{{ title }}</p>
        <p v-if="hint" class="mt-1 text-sm text-ink-muted">{{ hint }}</p>
        <div v-if="$slots.default" class="mt-4 flex justify-center gap-2">
            <slot />
        </div>
    </div>
</template>
