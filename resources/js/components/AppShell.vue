<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { RouterLink, RouterView } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useQuery } from '@tanstack/vue-query';
import {
    BookOpen,
    CalendarDays,
    ClipboardCheck,
    Refrigerator,
    Settings,
    ShoppingCart,
} from 'lucide-vue-next';
import { productsApi } from '@/api/products';
import { useRealtime } from '@/composables/useRealtime';
import { useSessionStore } from '@/stores/session';
import AppLogo from '@/components/AppLogo.vue';

/**
 * Frame of the signed-in area: navigation on the side on wide screens and
 * at the bottom on phones.
 */
const { t } = useI18n();
const session = useSessionStore();

const review = useQuery({ queryKey: ['review'], queryFn: () => productsApi.review() });
const reviewCount = computed(() => review.data.value?.meta.total ?? 0);

const links = computed(() => [
    { name: 'recipes', label: t('nav.recipes'), icon: BookOpen, badge: 0 },
    { name: 'plan', label: t('nav.plan'), icon: CalendarDays, badge: 0 },
    { name: 'shopping', label: t('nav.shopping'), icon: ShoppingCart, badge: 0 },
    { name: 'pantry', label: t('nav.pantry'), icon: Refrigerator, badge: 0 },
    { name: 'products', label: t('nav.products'), icon: ClipboardCheck, badge: reviewCount.value },
    { name: 'settings', label: t('nav.settings'), icon: Settings, badge: 0 },
]);

useRealtime();

onMounted(() => session.loadUser());
</script>

<template>
    <div class="min-h-dvh bg-paper text-ink lg:pl-56">
        <nav
            class="fixed inset-x-0 bottom-0 z-30 flex border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] lg:inset-y-0 lg:right-auto lg:w-56 lg:flex-col lg:gap-1 lg:border-t-0 lg:border-r lg:p-3"
        >
            <p
                class="hidden items-center gap-2.5 px-3 py-4 font-display text-xl font-bold tracking-tight lg:flex"
            >
                <AppLogo class="size-8" />{{ t('app.name') }}
            </p>
            <RouterLink
                v-for="link in links"
                :key="link.name"
                :to="{ name: link.name }"
                class="relative flex min-w-0 flex-1 flex-col items-center gap-1 px-1 py-2 text-[11px] text-ink-muted lg:flex-none lg:flex-row lg:gap-3 lg:rounded-full lg:px-4 lg:py-2 lg:text-sm lg:hover:bg-sunken"
                active-class="font-semibold text-accent-deep lg:bg-accent-soft lg:hover:bg-accent-soft"
            >
                <component :is="link.icon" class="size-5" />
                <span class="max-w-full truncate">{{ link.label }}</span>
                <span
                    v-if="link.badge > 0"
                    class="absolute top-1 right-1/4 rounded-full bg-accent px-1.5 text-[10px] font-semibold text-on-accent lg:static lg:ml-auto"
                >
                    {{ link.badge }}
                </span>
            </RouterLink>
        </nav>
        <main class="mx-auto max-w-5xl px-4 pt-6 pb-24 lg:px-8 lg:pb-10">
            <RouterView />
        </main>
    </div>
</template>
