<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { StockedNeed } from '@/api/types';
import { useAmounts } from '@/composables/useAmounts';

/**
 * Small note of what the meals need that is at home already, so whoever
 * shops knows it is counted on without it taking the room of a tile.
 */
const props = defineProps<{ needs: StockedNeed[] }>();

const { t } = useI18n();
const { amount } = useAmounts();

const text = computed(() =>
    props.needs
        .map((need) =>
            need.needed_amount === null
                ? need.name
                : `${need.name} ${amount(need.needed_amount, need.base_unit)}`,
        )
        .join(', '),
);
</script>

<template>
    <section class="mb-6 text-sm text-ink-muted">
        <h2 class="font-semibold">{{ t('shopping.inStock') }}</h2>
        <p>{{ text }}</p>
    </section>
</template>
