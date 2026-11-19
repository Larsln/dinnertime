<script setup lang="ts">
import { Label } from 'reka-ui';

/**
 * Caption above a form control.
 */
defineProps<{ for?: string }>();
</script>

<template>
    <Label :for="$props.for" class="mb-1 block text-sm font-medium text-ink">
        <slot />
    </Label>
</template>
