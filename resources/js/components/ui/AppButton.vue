<script setup lang="ts">
import { Primitive } from 'reka-ui';

/**
 * Button in one of the application's visual styles.
 */
withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
        size?: 'md' | 'sm' | 'icon';
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    { variant: 'secondary', size: 'md', type: 'button', disabled: false },
);

const VARIANTS = {
    primary: 'bg-accent text-on-accent hover:bg-accent-hover',
    secondary: 'border border-line-strong bg-surface text-ink hover:bg-sunken',
    ghost: 'text-ink-muted hover:bg-sunken',
    danger: 'bg-danger text-on-accent hover:bg-danger-deep',
};

const SIZES = {
    md: 'h-10 px-4 text-sm',
    sm: 'h-8 px-3 text-sm',
    icon: 'size-9',
};
</script>

<template>
    <Primitive
        as="button"
        :type="type"
        :disabled="disabled"
        class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-full font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50"
        :class="[VARIANTS[variant], SIZES[size]]"
    >
        <slot />
    </Primitive>
</template>
