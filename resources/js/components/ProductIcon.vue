<script setup lang="ts">
import { computed } from 'vue';
import { productIconShapes } from '@/lib/productIcons';

/**
 * Line icon of a product. Products without an icon of their own show the
 * generic icon of their store section.
 */
const props = defineProps<{ icon?: string | null; sectionKey?: string | null }>();

const shapes = computed(() => productIconShapes(props.icon, props.sectionKey));
</script>

<!-- eslint-disable vue/no-v-html -->
<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        v-html="shapes"
    />
</template>
