<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Heart, X } from 'lucide-vue-next';
import type { DinnerCard } from '@/api/types';
import DinnerRecipeCard from '@/components/DinnerRecipeCard.vue';
import AppButton from '@/components/ui/AppButton.vue';

/**
 * One recipe at a time to say yes or no to: with the two buttons, by
 * dragging the card to the right or left, or with the arrow keys.
 */
const SWIPE_DISTANCE = 96;
const DEGREES_PER_PIXEL = 0.05;

const props = defineProps<{ card: DinnerCard; position: number; total: number; disabled: boolean }>();

const emit = defineEmits<{ decide: [liked: boolean] }>();

const { t } = useI18n();

const offset = ref(0);
const dragStart = ref<number | null>(null);

const pull = computed(() => Math.min(Math.abs(offset.value) / SWIPE_DISTANCE, 1));

function decide(liked: boolean): void {
    if (!props.disabled) {
        emit('decide', liked);
    }
}

function onPointerDown(event: PointerEvent): void {
    if (props.disabled || event.button !== 0) {
        return;
    }

    dragStart.value = event.clientX;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent): void {
    if (dragStart.value !== null) {
        offset.value = event.clientX - dragStart.value;
    }
}

function onPointerUp(): void {
    const distance = offset.value;
    const wasDragging = dragStart.value !== null;

    dragStart.value = null;
    offset.value = 0;

    if (wasDragging && Math.abs(distance) >= SWIPE_DISTANCE) {
        decide(distance > 0);
    }
}

function onPointerCancel(): void {
    dragStart.value = null;
    offset.value = 0;
}

function onKeyDown(event: KeyboardEvent): void {
    const isTyping = event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement;

    if (isTyping || event.altKey || event.ctrlKey || event.metaKey) {
        return;
    }

    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        decide(event.key === 'ArrowRight');
    }
}

onMounted(() => window.addEventListener('keydown', onKeyDown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeyDown));
</script>

<template>
    <div class="mx-auto w-full max-w-sm space-y-4">
        <p class="text-center text-sm font-medium text-ink-muted tabular-nums" aria-live="polite">
            {{ t('dinner.progress', { position, total }) }}
        </p>
        <div
            :key="card.recipe.id"
            class="relative cursor-grab touch-pan-y select-none active:cursor-grabbing"
            :class="{ 'transition-transform duration-200': dragStart === null }"
            :style="{ transform: `translateX(${offset}px) rotate(${offset * DEGREES_PER_PIXEL}deg)` }"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerCancel"
        >
            <DinnerRecipeCard :recipe="card.recipe" :missing-count="card.missing_count" />
            <span
                v-if="offset !== 0"
                class="absolute top-4 rounded-full px-4 py-1.5 font-display text-lg font-bold"
                :class="offset > 0 ? 'left-4 bg-herb text-surface' : 'right-4 bg-ink text-surface'"
                :style="{ opacity: pull }"
            >
                {{ offset > 0 ? t('dinner.yes') : t('dinner.no') }}
            </span>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <AppButton size="lg" :disabled="disabled" @click="decide(false)">
                <X class="size-5" />{{ t('dinner.no') }}
            </AppButton>
            <AppButton size="lg" variant="primary" :disabled="disabled" @click="decide(true)">
                <Heart class="size-5" />{{ t('dinner.yes') }}
            </AppButton>
        </div>
        <p class="text-center text-xs text-ink-muted">{{ t('dinner.swipeHint') }}</p>
    </div>
</template>
