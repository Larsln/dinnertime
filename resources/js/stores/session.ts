import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { authApi } from '@/api/auth';
import type { ServerInfo, Session, User } from '@/api/types';

const TOKEN_KEY = 'mealplanner.token';
const DEVICE_NAME = 'Web';

/**
 * Signed-in user, API token and what the server offers for signing in.
 */
export const useSessionStore = defineStore('session', () => {
    const token = ref<string | null>(localStorage.getItem(TOKEN_KEY));
    const user = ref<User | null>(null);
    const server = ref<ServerInfo | null>(null);

    const isSignedIn = computed(() => token.value !== null);

    function start(session: Session): void {
        token.value = session.token;
        user.value = session.user;
        localStorage.setItem(TOKEN_KEY, session.token);
    }

    function clear(): void {
        token.value = null;
        user.value = null;
        localStorage.removeItem(TOKEN_KEY);
    }

    async function loadServer(): Promise<void> {
        server.value ??= await authApi.server();
    }

    async function loadUser(): Promise<void> {
        if (token.value !== null && user.value === null) {
            user.value = await authApi.user();
        }
    }

    async function login(username: string, password: string): Promise<void> {
        start(await authApi.login(username, password, DEVICE_NAME));
    }

    async function loginWithIdToken(idToken: string): Promise<void> {
        start(await authApi.loginWithIdToken(idToken, DEVICE_NAME));
    }

    async function logout(): Promise<void> {
        await authApi.logout().catch(() => undefined);
        clear();
    }

    return { token, user, server, isSignedIn, clear, loadServer, loadUser, login, loginWithIdToken, logout };
});
