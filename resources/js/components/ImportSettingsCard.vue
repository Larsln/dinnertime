<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { FileUp, Trash2 } from 'lucide-vue-next';
import { importSettingsApi, type ImportSettings } from '@/api/settings';
import { useToasts } from '@/composables/useToasts';
import AppButton from '@/components/ui/AppButton.vue';
import FileButton from '@/components/ui/FileButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import SwitchField from '@/components/ui/SwitchField.vue';

/**
 * Workarounds for links the import cannot fetch on its own: identifying
 * as a browser and the cookies of a signed-in browser.
 */
const QUERY_KEY = ['import-settings'];

const { t } = useI18n();
const queryClient = useQueryClient();
const { showError } = useToasts();

const settings = useQuery({ queryKey: QUERY_KEY, queryFn: importSettingsApi.get });

function store(updated: ImportSettings): void {
    queryClient.setQueryData(QUERY_KEY, updated);
}

const setBrowser = useMutation({
    mutationFn: importSettingsApi.setIdentifyAsBrowser,
    onSuccess: store,
    onError: showError,
});

const uploadCookies = useMutation({
    mutationFn: importSettingsApi.uploadCookies,
    onSuccess: store,
    onError: showError,
});
const deleteCookies = useMutation({
    mutationFn: importSettingsApi.deleteCookies,
    onSuccess: store,
    onError: showError,
});

const identifiesAsBrowser = computed({
    get: () => settings.data.value?.identify_as_browser ?? false,
    set: (enabled: boolean) => setBrowser.mutate(enabled),
});
</script>

<template>
    <section class="rounded-card border border-line bg-surface p-4">
        <h2 class="mb-2 font-display text-lg font-bold">{{ t('settings.import.title') }}</h2>
        <SwitchField
            v-model="identifiesAsBrowser"
            :label="t('settings.import.browser')"
            :hint="t('settings.import.browserHint')"
        />
        <div class="mt-3 border-t border-line pt-3">
            <p class="text-sm font-medium text-ink">
                {{ t('settings.import.cookies') }}
                <StatusBadge v-if="settings.data.value?.has_cookies" tone="success" class="ml-2">
                    {{ t('settings.import.cookiesStored') }}
                </StatusBadge>
            </p>
            <p class="mt-1 text-sm text-ink-muted">{{ t('settings.import.cookiesHint') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <FileButton accept=".txt,text/plain" @select="(file) => uploadCookies.mutate(file)">
                    <FileUp class="size-4" />{{ t('settings.import.uploadCookies') }}
                </FileButton>
                <AppButton
                    v-if="settings.data.value?.has_cookies"
                    variant="ghost"
                    @click="deleteCookies.mutate()"
                >
                    <Trash2 class="size-4" />{{ t('settings.import.deleteCookies') }}
                </AppButton>
            </div>
        </div>
    </section>
</template>
