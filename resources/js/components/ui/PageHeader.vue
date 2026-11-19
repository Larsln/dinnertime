<script setup lang="ts">
/**
 * Title of a page with room for actions on the right.
 */
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-bold tracking-tight text-ink">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-ink-muted">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </header>
</template>
