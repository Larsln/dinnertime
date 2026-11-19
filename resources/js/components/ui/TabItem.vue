<script setup lang="ts">
import { TabsTrigger } from 'reka-ui';

/**
 * One tab of a segmented control.
 */
defineProps<{ value: string }>();
</script>

<template>
    <TabsTrigger
        :value="value"
        class="cursor-pointer rounded-full px-4 py-1.5 text-sm font-medium text-ink-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent data-[state=active]:bg-surface data-[state=active]:font-semibold data-[state=active]:text-ink data-[state=active]:shadow-sm data-[state=active]:ring-1 data-[state=active]:ring-line"
    >
        <slot />
    </TabsTrigger>
</template>
