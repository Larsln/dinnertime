<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { SliderRoot, SliderThumb, SliderTrack } from 'reka-ui';
import { Star, X } from 'lucide-vue-next';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * Rating shown and named as zero to five stars in half steps; stored as
 * points from 0 to 10, one per half star.
 * Dragging or tapping across the stars sets the value, which is stored
 * when released and can be withdrawn again.
 */
const model = defineModel<number | null>({ required: true });

defineProps<{ label: string }>();

const MAXIMUM = 10;
const STARS = [1, 2, 3, 4, 5];

const { t } = useI18n();

const draft = ref<number | null>(model.value);

watch(model, (value) => {
    draft.value = value;
});

const position = computed({
    get: () => [draft.value ?? 0],
    set: (values: number[] | undefined) => {
        draft.value = values?.[0] ?? null;
    },
});

function commit(values: number[]): void {
    model.value = values[0] ?? null;
}

/**
 * Share of a star that is filled: none, half or all of it.
 */
function fillOf(star: number): string {
    const points = (draft.value ?? 0) - (star - 1) * 2;

    return points >= 2 ? '100%' : points === 1 ? '50%' : '0%';
}
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="relative rounded-lg focus-within:outline-2 focus-within:outline-offset-4 focus-within:outline-emerald-600"
        >
            <div class="flex gap-1" aria-hidden="true">
                <span v-for="star in STARS" :key="star" class="relative size-8">
                    <Star class="size-8 text-stone-300" />
                    <span class="absolute inset-0 overflow-hidden" :style="{ width: fillOf(star) }">
                        <Star class="size-8 fill-amber-400 text-amber-400" />
                    </span>
                </span>
            </div>
            <SliderRoot
                v-model="position"
                :min="0"
                :max="MAXIMUM"
                :step="1"
                class="absolute inset-0 flex cursor-pointer touch-none items-center"
                @value-commit="commit"
            >
                <SliderTrack class="h-full grow" />
                <SliderThumb :aria-label="label" class="block size-1 opacity-0 focus:outline-none" />
            </SliderRoot>
        </div>
        <span class="text-sm font-medium text-stone-800 tabular-nums">
            {{
                draft === null
                    ? t('recipes.notRated')
                    : t('recipes.ratingValue', {
                          value: formatNumber(draft / 2, locale),
                          maximum: MAXIMUM / 2,
                      })
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
