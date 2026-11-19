<script setup lang="ts">
/**
 * Small coloured label for a state or a hint.
 */
withDefaults(defineProps<{ tone?: 'neutral' | 'info' | 'warning' | 'danger' | 'success' }>(), {
    tone: 'neutral',
});

const TONES = {
    neutral: 'bg-sunken text-ink-muted',
    info: 'bg-accent-soft text-accent-deep',
    warning: 'bg-warn-soft text-warn-deep',
    danger: 'bg-danger-soft text-danger-deep',
    success: 'bg-herb-soft text-herb',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
        :class="TONES[tone]"
    >
        <slot />
    </span>
</template>
