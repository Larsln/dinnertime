import { ref, watch, type Ref } from 'vue';

/**
 * Copy of a ref that follows the original after it stopped changing.
 */
export function useDebounced<T>(source: Ref<T>, milliseconds = 250): Ref<T> {
    const debounced = ref(source.value) as Ref<T>;
    let timer: ReturnType<typeof setTimeout> | undefined;

    watch(source, (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            debounced.value = value;
        }, milliseconds);
    });

    return debounced;
}
