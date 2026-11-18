<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { fromIsoDate } from '@/lib/dates';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * The seven days of a week side by side. Today is highlighted, days with
 * planned meals carry a dot, and choosing a day jumps to it.
 */
defineProps<{ days: string[]; today: string; planned: Set<string> }>();

defineEmits<{ select: [day: string] }>();

const { locale } = useI18n();

function weekday(day: string): string {
    return new Intl.DateTimeFormat(locale.value, { weekday: 'short' }).format(fromIsoDate(day));
}
</script>

<template>
    <div class="mb-6 grid grid-cols-7 gap-1 rounded-2xl border border-stone-200 bg-white p-2">
        <AppButton
            v-for="day in days"
            :key="day"
            variant="ghost"
            class="h-auto flex-col gap-0.5 px-0 py-2"
            :class="{ 'bg-emerald-50 text-emerald-700': day === today }"
            @click="$emit('select', day)"
        >
            <span class="text-xs font-normal text-stone-500">{{ weekday(day) }}</span>
            <span class="text-base font-semibold tabular-nums">{{ fromIsoDate(day).getDate() }}</span>
            <span
                class="size-1.5 rounded-full"
                :class="planned.has(day) ? 'bg-emerald-600' : 'bg-transparent'"
            />
        </AppButton>
    </div>
</template>
