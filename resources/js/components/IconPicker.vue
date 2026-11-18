<script setup lang="ts">
import { computed } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';
import { PRODUCT_ICONS } from '@/lib/productIcons';
import FieldLabel from '@/components/ui/FieldLabel.vue';

/**
 * Grid of line icons from which the picture of a product is chosen.
 */
const model = defineModel<string | null>({ required: true });

defineProps<{ label: string }>();

const selected = computed({
    get: () => model.value ?? undefined,
    set: (key: string | undefined) => {
        model.value = key || null;
    },
});
</script>

<template>
    <div>
        <FieldLabel>{{ label }}</FieldLabel>
        <ToggleGroupRoot v-model="selected" type="single" class="flex flex-wrap gap-1">
            <ToggleGroupItem
                v-for="(icon, key) in PRODUCT_ICONS"
                :key="key"
                :value="key"
                :aria-label="key"
                class="flex size-9 cursor-pointer items-center justify-center rounded-lg border border-transparent text-stone-500 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-600 data-[state=on]:border-emerald-600 data-[state=on]:bg-emerald-50 data-[state=on]:text-emerald-700"
            >
                <component :is="icon" class="size-5" stroke-width="1.5" />
            </ToggleGroupItem>
        </ToggleGroupRoot>
    </div>
</template>
