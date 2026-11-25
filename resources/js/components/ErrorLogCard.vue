<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Download, Trash2 } from 'lucide-vue-next';
import { clearErrorLog, errorLogText, readErrorLog } from '@/lib/errorLog';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * The errors this browser ran into, newest first, with a download as a
 * text file to pass on.
 */
const VISIBLE = 5;

const { t, locale } = useI18n();
const entries = ref(readErrorLog().reverse());

function download(): void {
    const link = document.createElement('a');

    link.href = URL.createObjectURL(new Blob([errorLogText()], { type: 'text/plain' }));
    link.download = `dinnertime-errors-${new Date().toISOString().slice(0, 10)}.txt`;
    link.click();
    URL.revokeObjectURL(link.href);
}

function clear(): void {
    clearErrorLog();
    entries.value = [];
}

function formatTime(time: string): string {
    return new Date(time).toLocaleString(locale.value, { dateStyle: 'short', timeStyle: 'medium' });
}
</script>

<template>
    <section class="rounded-card border border-line bg-surface p-4">
        <h2 class="mb-1 font-display text-lg font-bold">{{ t('settings.errorLog.title') }}</h2>
        <p class="text-sm text-ink-muted">
            {{ entries.length ? t('settings.errorLog.hint') : t('settings.errorLog.empty') }}
        </p>
        <ul v-if="entries.length" class="mt-3 space-y-2 text-sm">
            <li v-for="entry in entries.slice(0, VISIBLE)" :key="entry.time + entry.context">
                <p class="text-ink-muted">{{ formatTime(entry.time) }} · {{ entry.context }}</p>
                <p class="break-words text-ink">{{ entry.message }}</p>
            </li>
        </ul>
        <p v-if="entries.length > VISIBLE" class="mt-2 text-sm text-ink-muted">
            {{ t('settings.errorLog.more', { count: entries.length - VISIBLE }) }}
        </p>
        <div v-if="entries.length" class="mt-4 flex flex-wrap gap-2">
            <AppButton @click="download"
                ><Download class="size-4" />{{ t('settings.errorLog.export') }}</AppButton
            >
            <AppButton variant="ghost" @click="clear"
                ><Trash2 class="size-4" />{{ t('settings.errorLog.clear') }}</AppButton
            >
        </div>
    </section>
</template>
