<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { startOidcLogin } from '@/lib/oidc';
import { useToasts } from '@/composables/useToasts';
import { useSessionStore } from '@/stores/session';
import AppButton from '@/components/ui/AppButton.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Sign-in with a local account or, if the server offers one, the
 * identity provider.
 */
const { t } = useI18n();
const router = useRouter();
const session = useSessionStore();
const { showError } = useToasts();

const username = ref('');
const password = ref('');
const isSubmitting = ref(false);

onMounted(() => session.loadServer().catch(showError));

async function submit(): Promise<void> {
    isSubmitting.value = true;

    try {
        await session.login(username.value, password.value);
        await router.push({ name: 'recipes' });
    } catch (error) {
        showError(error);
    } finally {
        isSubmitting.value = false;
    }
}

async function signInWithProvider(): Promise<void> {
    if (session.server?.oidc) {
        await startOidcLogin(session.server.oidc).catch(showError);
    }
}
</script>

<template>
    <div class="flex min-h-dvh items-center justify-center bg-stone-50 px-4">
        <form
            class="w-full max-w-sm space-y-4 rounded-2xl border border-stone-200 bg-white p-6"
            @submit.prevent="submit"
        >
            <h1 class="text-xl font-semibold text-stone-900">{{ session.server?.name ?? t('app.name') }}</h1>
            <TextField v-model="username" :label="t('auth.username')" autocomplete="username" required />
            <TextField
                v-model="password"
                type="password"
                :label="t('auth.password')"
                autocomplete="current-password"
                required
            />
            <AppButton type="submit" variant="primary" class="w-full" :disabled="isSubmitting">{{
                t('auth.signIn')
            }}</AppButton>
            <AppButton v-if="session.server?.oidc" class="w-full" @click="signInWithProvider">
                {{ t('auth.signInWithProvider') }}
            </AppButton>
        </form>
    </div>
</template>
