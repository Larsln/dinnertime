import { createRouter, createWebHistory } from 'vue-router';
import { useSessionStore } from './stores/session';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: () => import('./pages/LoginPage.vue') },
        {
            path: '/oidc/callback',
            name: 'oidc-callback',
            component: () => import('./pages/OidcCallbackPage.vue'),
        },
        {
            path: '/',
            component: () => import('./components/AppShell.vue'),
            meta: { requiresAuth: true },
            children: [
                { path: '', redirect: { name: 'recipes' } },
                { path: 'recipes', name: 'recipes', component: () => import('./pages/RecipesPage.vue') },
                {
                    path: 'recipes/new',
                    name: 'recipe-new',
                    component: () => import('./pages/RecipeEditPage.vue'),
                },
                {
                    path: 'recipes/:id(\\d+)',
                    name: 'recipe',
                    component: () => import('./pages/RecipeDetailPage.vue'),
                    props: (route) => ({ id: Number(route.params.id) }),
                },
                {
                    path: 'recipes/:id(\\d+)/edit',
                    name: 'recipe-edit',
                    component: () => import('./pages/RecipeEditPage.vue'),
                    props: (route) => ({ id: Number(route.params.id) }),
                },
                { path: 'plan', name: 'plan', component: () => import('./pages/PlanPage.vue') },
                { path: 'shopping', name: 'shopping', component: () => import('./pages/ShoppingPage.vue') },
                { path: 'pantry', name: 'pantry', component: () => import('./pages/PantryPage.vue') },
                { path: 'products', name: 'products', component: () => import('./pages/ProductsPage.vue') },
                { path: 'settings', name: 'settings', component: () => import('./pages/SettingsPage.vue') },
            ],
        },
        { path: '/:pathMatch(.*)*', redirect: { name: 'recipes' } },
    ],
});

router.beforeEach((to) => {
    const session = useSessionStore();

    if (to.matched.some((record) => record.meta.requiresAuth) && !session.isSignedIn) {
        return { name: 'login' };
    }

    if (to.name === 'login' && session.isSignedIn) {
        return { name: 'recipes' };
    }

    return true;
});
