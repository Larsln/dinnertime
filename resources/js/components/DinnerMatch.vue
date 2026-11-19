<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { CalendarPlus } from 'lucide-vue-next';
import type { RecipeSummary } from '@/api/types';
import DinnerRecipeCard from '@/components/DinnerRecipeCard.vue';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * The recipe the round agreed on, to put on today's plan or to drop.
 */
defineProps<{ recipe: RecipeSummary; disabled: boolean }>();

defineEmits<{ plan: []; discard: [] }>();

const { t } = useI18n();
</script>

<template>
    <div class="mx-auto w-full max-w-sm space-y-4">
        <div class="text-center">
            <p class="text-xs font-bold tracking-wider text-accent-deep uppercase">{{ t('dinner.match') }}</p>
            <p class="font-display text-2xl font-bold tracking-tight text-ink">
                {{ t('dinner.matchTitle') }}
            </p>
        </div>
        <DinnerRecipeCard :recipe="recipe" />
        <div class="grid gap-3">
            <AppButton size="lg" variant="primary" :disabled="disabled" @click="$emit('plan')">
                <CalendarPlus class="size-5" />{{ t('dinner.planToday') }}
            </AppButton>
            <AppButton variant="ghost" :disabled="disabled" @click="$emit('discard')">
                {{ t('dinner.discard') }}
            </AppButton>
        </div>
    </div>
</template>
