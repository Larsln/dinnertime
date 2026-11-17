import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import App from './App.vue';
import { configureApi } from './api/client';
import { currentLocale, i18n } from './i18n';
import { router } from './router';
import { useSessionStore } from './stores/session';

const app = createApp(App);

app.use(createPinia());
app.use(i18n);
app.use(VueQueryPlugin, {
    queryClientConfig: { defaultOptions: { queries: { retry: false, refetchOnWindowFocus: true } } },
});

const session = useSessionStore();

configureApi({
    token: () => session.token,
    locale: currentLocale,
    onUnauthorized: () => {
        session.clear();
        router.push({ name: 'login' });
    },
});

document.documentElement.lang = currentLocale();

app.use(router);
app.mount('#app');
