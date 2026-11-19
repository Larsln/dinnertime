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
    <div class="mb-6 grid grid-cols-7 gap-1 rounded-card border border-line bg-surface p-2">
        <AppButton
            v-for="day in days"
            :key="day"
            variant="ghost"
            class="h-auto! flex-col gap-0.5! rounded-tile! px-0! py-2"
            :class="day === today ? 'bg-accent! text-on-accent! hover:bg-accent-hover!' : 'text-ink!'"
            @click="$emit('select', day)"
        >
            <span
                class="text-xs font-semibold tracking-wider uppercase"
                :class="day === today ? 'text-on-accent' : 'text-ink-muted'"
                >{{ weekday(day) }}</span
            >
            <span class="font-display text-xl leading-none font-bold tabular-nums">{{
                fromIsoDate(day).getDate()
            }}</span>
            <span
                class="size-1.5 rounded-full"
                :class="planned.has(day) ? (day === today ? 'bg-on-accent' : 'bg-accent') : 'bg-transparent'"
            />
        </AppButton>
    </div>
</template>
