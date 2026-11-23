<script setup lang="ts">
import type { Component } from 'vue';
import { Archive, Heart, History, Link2, Star, Zap } from 'lucide-vue-next';
import type { PlanReason } from '@/api/types';
import { usePlanReasons } from '@/composables/usePlanReasons';

/**
 * Why a meal goes with the rest of the plan, as a short line per reason.
 * What it shares with other meals comes first.
 */
defineProps<{ reasons: PlanReason[] }>();

const ICONS: Record<PlanReason['type'], Component> = {
    shares: Link2,
    pantry: Archive,
    favorite: Heart,
    rating: Star,
    last_planned: History,
    not_recent: History,
    quick: Zap,
};

const { describe } = usePlanReasons();
</script>

<template>
    <ul v-if="reasons.length > 0" class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-muted">
        <li v-for="(reason, index) in reasons" :key="index" class="inline-flex items-center gap-1">
            <component
                :is="ICONS[reason.type]"
                class="size-3.5 shrink-0"
                :class="{ 'text-accent-deep': reason.type === 'shares' || reason.type === 'pantry' }"
                aria-hidden="true"
            />
            <span>{{ describe(reason) }}</span>
        </li>
    </ul>
</template>
