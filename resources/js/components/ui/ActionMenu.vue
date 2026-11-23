<script setup lang="ts">
import type { Component } from 'vue';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import { Ellipsis } from 'lucide-vue-next';

/**
 * Button that opens a short list of actions. Destructive actions are
 * drawn in the danger color.
 */
export interface MenuAction {
    label: string;
    icon?: Component;
    danger?: boolean;
    select: () => void;
}

defineProps<{ label: string; actions: MenuAction[] }>();
</script>

<template>
    <DropdownMenuRoot>
        <DropdownMenuTrigger
            :aria-label="label"
            :title="label"
            class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-full text-ink-muted transition-colors hover:bg-sunken focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
        >
            <Ellipsis class="size-4" />
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                align="end"
                :side-offset="4"
                class="z-50 min-w-48 rounded-tile border border-line bg-surface p-1 text-sm shadow-lg"
            >
                <DropdownMenuItem
                    v-for="action in actions"
                    :key="action.label"
                    class="flex cursor-pointer items-center gap-2 rounded-field px-3 py-2 outline-none"
                    :class="
                        action.danger
                            ? 'text-danger-deep data-[highlighted]:bg-danger-soft'
                            : 'text-ink data-[highlighted]:bg-accent-soft'
                    "
                    @select="action.select"
                >
                    <component :is="action.icon" v-if="action.icon" class="size-4" />{{ action.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
