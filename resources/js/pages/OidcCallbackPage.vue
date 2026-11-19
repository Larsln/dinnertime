<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { completeOidcLogin } from '@/lib/oidc';
import { useToasts } from '@/composables/useToasts';
import { useSessionStore } from '@/stores/session';

/**
 * Landing page the identity provider redirects to after sign-in.
 */
const { t } = useI18n();
const router = useRouter();
const session = useSessionStore();
const { showError } = useToasts();

onMounted(async () => {
    try {
        const idToken = await completeOidcLogin(new URLSearchParams(window.location.search));
        await session.loginWithIdToken(idToken);
        await router.replace({ name: 'recipes' });
    } catch (error) {
        showError(error);
        await router.replace({ name: 'login' });
    }
});
</script>

<template>
    <p class="flex min-h-dvh items-center justify-center text-ink-muted">{{ t('auth.signingIn') }}</p>
</template>
