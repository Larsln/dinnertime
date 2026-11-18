import { onBeforeUnmount, onMounted } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { api, data } from '@/api/client';
import { readEventStream } from '@/lib/eventStream';

interface RealtimeAccess {
    url: string;
    token: string;
}

const RETRY_MILLISECONDS = 5000;

/**
 * Cached queries that show each area of the household's data.
 */
const QUERY_KEYS: Record<string, string[]> = {
    shopping: ['shopping-list'],
    pantry: ['pantry'],
    plan: ['meal-plan'],
    recipes: ['recipes', 'recipe'],
    products: ['review', 'products', 'product'],
    tools: ['tools'],
};

/**
 * Keeps the page up to date while it is open: listens to the server's
 * change events and reloads the data of the areas they name. Reconnects
 * after an interruption and does nothing on servers without realtime.
 */
export function useRealtime(): void {
    const queryClient = useQueryClient();
    const abort = new AbortController();

    function refresh(message: string): void {
        const { areas } = JSON.parse(message) as { areas: string[] };

        for (const key of areas.flatMap((area) => QUERY_KEYS[area] ?? [])) {
            queryClient.invalidateQueries({ queryKey: [key] });
        }
    }

    async function listen(): Promise<void> {
        while (!abort.signal.aborted) {
            try {
                const access = await data(api.get<{ data: RealtimeAccess }>('/realtime'));
                await readEventStream(access.url, access.token, abort.signal, refresh);
            } catch {
                await new Promise((resolve) => setTimeout(resolve, RETRY_MILLISECONDS));
            }
        }
    }

    onMounted(listen);
    onBeforeUnmount(() => abort.abort());
}
