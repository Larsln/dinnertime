<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { RotateCw } from 'lucide-vue-next';
import { importsApi } from '@/api/recipes';
import type { ImportStatus, RecipeImport } from '@/api/types';
import { useToasts } from '@/composables/useToasts';
import AppButton from '@/components/ui/AppButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Imports a recipe from a link and shows the recent imports, refreshing
 * while one of them is still running.
 */
const POLL_MILLISECONDS = 3000;
const VISIBLE_IMPORTS = 5;

const TONES: Record<ImportStatus, 'neutral' | 'info' | 'success' | 'danger'> = {
    pending: 'neutral',
    processing: 'info',
    completed: 'success',
    failed: 'danger',
};

const { t } = useI18n();
const { showError } = useToasts();
const queryClient = useQueryClient();

const url = ref('');

function isRunning(entry: RecipeImport): boolean {
    return entry.status === 'pending' || entry.status === 'processing';
}

const imports = useQuery({
    queryKey: ['imports'],
    queryFn: importsApi.list,
    refetchInterval: (query) => (query.state.data?.some(isRunning) ? POLL_MILLISECONDS : false),
});

const recent = computed(() => (imports.data.value ?? []).slice(0, VISIBLE_IMPORTS));
const runningCount = computed(() => (imports.data.value ?? []).filter(isRunning).length);

watch(runningCount, (count, previous) => {
    if (count < previous) {
        queryClient.invalidateQueries({ queryKey: ['recipes'] });
        queryClient.invalidateQueries({ queryKey: ['review'] });
    }
});

const create = useMutation({
    mutationFn: () => importsApi.create(url.value.trim()),
    onSuccess: () => {
        url.value = '';
        queryClient.invalidateQueries({ queryKey: ['imports'] });
    },
    onError: showError,
});

const retry = useMutation({
    mutationFn: (id: number) => importsApi.retry(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['imports'] }),
    onError: showError,
});
</script>

<template>
    <section class="mb-6 rounded-2xl border border-stone-200 bg-white p-4">
        <form class="flex items-end gap-2" @submit.prevent="create.mutate()">
            <TextField
                v-model="url"
                class="flex-1"
                type="url"
                :label="t('imports.label')"
                :placeholder="t('imports.placeholder')"
                required
            />
            <AppButton type="submit" variant="primary" :disabled="create.isPending.value">{{
                t('imports.submit')
            }}</AppButton>
        </form>
        <ul v-if="recent.length > 0" class="mt-3 divide-y divide-stone-100">
            <li v-for="entry in recent" :key="entry.id" class="flex items-center gap-3 py-2 text-sm">
                <StatusBadge :tone="TONES[entry.status]">
                    {{
                        entry.stage ? t(`imports.stage.${entry.stage}`) : t(`imports.status.${entry.status}`)
                    }}
                </StatusBadge>
                <RouterLink
                    v-if="entry.recipe"
                    :to="{ name: 'recipe', params: { id: entry.recipe.id } }"
                    class="min-w-0 flex-1 truncate font-medium text-emerald-700 hover:underline"
                >
                    {{ entry.recipe.title }}
                </RouterLink>
                <span v-else class="min-w-0 flex-1 truncate text-stone-600">{{
                    entry.error_message ?? entry.url
                }}</span>
                <AppButton
                    v-if="entry.status === 'failed'"
                    size="icon"
                    variant="ghost"
                    :aria-label="t('imports.retry')"
                    @click="retry.mutate(entry.id)"
                >
                    <RotateCw class="size-4" />
                </AppButton>
            </li>
        </ul>
    </section>
</template>
