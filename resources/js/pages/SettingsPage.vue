<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useQueryClient } from '@tanstack/vue-query';
import { SUPPORTED_LOCALES, currentLocale, setLocale, type Locale } from '@/i18n';
import { useSessionStore } from '@/stores/session';
import HouseholdSettingsCard from '@/components/HouseholdSettingsCard.vue';
import ImportSettingsCard from '@/components/ImportSettingsCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SelectField from '@/components/ui/SelectField.vue';

/**
 * Account, interface language, sign-out and how links are imported.
 */
const { t } = useI18n();
const router = useRouter();
const session = useSessionStore();
const queryClient = useQueryClient();

const localeOptions = SUPPORTED_LOCALES.map((value) => ({ value, label: t(`locales.${value}`) }));

const locale = computed({
    get: (): Locale | null => currentLocale(),
    set: (value: Locale | null) => {
        if (value) {
            setLocale(value);
            queryClient.invalidateQueries();
        }
    },
});

async function signOut(): Promise<void> {
    await session.logout();
    queryClient.clear();
    await router.push({ name: 'login' });
}
</script>

<template>
    <PageHeader :title="t('nav.settings')" />
    <div class="max-w-md space-y-6">
        <section class="rounded-card border border-line bg-surface p-4">
            <h2 class="mb-2 font-display text-lg font-bold">{{ t('settings.account') }}</h2>
            <p class="text-sm text-ink">{{ session.user?.name }}</p>
            <p class="text-sm text-ink-muted">{{ session.user?.username }}</p>
            <AppButton class="mt-4" @click="signOut">{{ t('auth.signOut') }}</AppButton>
        </section>
        <section class="rounded-card border border-line bg-surface p-4">
            <SelectField v-model="locale" :options="localeOptions" :label="t('settings.language')" />
        </section>
        <HouseholdSettingsCard />
        <ImportSettingsCard />
    </div>
</template>
