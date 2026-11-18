<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { SliderRange, SliderRoot, SliderThumb, SliderTrack } from 'reka-ui';
import { X } from 'lucide-vue-next';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * Slider from 0 (bad) to 10 (good). The rating is stored when the thumb
 * is released and can be withdrawn again.
 */
const model = defineModel<number | null>({ required: true });

defineProps<{ label: string }>();

const MAXIMUM = 10;
const MIDDLE = 5;

const { t } = useI18n();

const draft = ref<number | null>(model.value);

watch(model, (value) => {
    draft.value = value;
});

const position = computed({
    get: () => [draft.value ?? MIDDLE],
    set: (values: number[] | undefined) => {
        draft.value = values?.[0] ?? null;
    },
});

function commit(values: number[]): void {
    model.value = values[0] ?? null;
}
</script>

<template>
    <div class="flex items-center gap-3">
        <SliderRoot
            v-model="position"
            :min="0"
            :max="MAXIMUM"
            :step="1"
            class="relative flex h-6 w-56 max-w-full touch-none items-center"
            @value-commit="commit"
        >
            <SliderTrack class="relative h-1.5 grow rounded-full bg-stone-200">
                <SliderRange
                    class="absolute h-full rounded-full"
                    :class="draft === null ? 'bg-stone-300' : 'bg-emerald-600'"
                />
            </SliderTrack>
            <SliderThumb
                :aria-label="label"
                class="block size-5 cursor-grab rounded-full border-2 bg-white shadow focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                :class="draft === null ? 'border-stone-400' : 'border-emerald-600'"
            />
        </SliderRoot>
        <span class="w-24 text-sm font-medium text-stone-800 tabular-nums">
            {{
                draft === null
                    ? t('recipes.notRated')
                    : t('recipes.ratingValue', { value: draft, maximum: MAXIMUM })
            }}
        </span>
        <AppButton
            v-if="model !== null"
            size="icon"
            variant="ghost"
            :aria-label="t('recipes.withdrawRating')"
            @click="model = null"
        >
            <X class="size-4" />
        </AppButton>
    </div>
</template>
