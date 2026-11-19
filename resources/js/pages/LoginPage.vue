<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { startOidcLogin } from '@/lib/oidc';
import { useToasts } from '@/composables/useToasts';
import { useSessionStore } from '@/stores/session';
import AppLogo from '@/components/AppLogo.vue';
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
const offersPassword = computed(() => session.server?.auth_methods.includes('password') ?? true);

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
    <div class="flex min-h-dvh items-center justify-center bg-paper px-4 text-ink">
        <form
            class="w-full max-w-sm space-y-4 rounded-card border border-line bg-surface p-6"
            @submit.prevent="submit"
        >
            <div class="flex items-center gap-3">
                <AppLogo class="size-11" />
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink">
                    {{ session.server?.name ?? t('app.name') }}
                </h1>
            </div>
            <template v-if="offersPassword">
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
            </template>
            <AppButton
                v-if="session.server?.oidc"
                :variant="offersPassword ? 'secondary' : 'primary'"
                class="w-full"
                @click="signInWithProvider"
            >
                {{ t('auth.signInWithProvider') }}
            </AppButton>
        </form>
    </div>
</template>
