<script setup lang="ts">
/**
 * Small coloured label for a state or a hint.
 */
withDefaults(defineProps<{ tone?: 'neutral' | 'info' | 'warning' | 'danger' | 'success' }>(), {
    tone: 'neutral',
});

const TONES = {
    neutral: 'bg-stone-100 text-stone-700',
    info: 'bg-sky-100 text-sky-800',
    warning: 'bg-amber-100 text-amber-800',
    danger: 'bg-red-100 text-red-800',
    success: 'bg-emerald-100 text-emerald-800',
};
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="TONES[tone]">
        <slot />
    </span>
</template>
