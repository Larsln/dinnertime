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
    primary: 'bg-emerald-600 text-white hover:bg-emerald-700',
    secondary: 'border border-stone-300 bg-white text-stone-800 hover:bg-stone-50',
    ghost: 'text-stone-600 hover:bg-stone-100',
    danger: 'bg-red-600 text-white hover:bg-red-700',
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
        class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
        :class="[VARIANTS[variant], SIZES[size]]"
    >
        <slot />
    </Primitive>
</template>
