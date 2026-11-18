<script setup lang="ts">
import { useId } from 'vue';
import { Label, SwitchRoot, SwitchThumb } from 'reka-ui';

/**
 * On/off switch with a title and an optional one-line explanation.
 */
const model = defineModel<boolean>({ required: true });

withDefaults(defineProps<{ label: string; hint?: string }>(), { hint: undefined });

const id = useId();
</script>

<template>
    <div class="flex items-center justify-between gap-4 py-2">
        <Label :for="id" class="cursor-pointer">
            <span class="block text-sm font-medium text-stone-900">{{ label }}</span>
            <span v-if="hint" class="block text-sm text-stone-500">{{ hint }}</span>
        </Label>
        <SwitchRoot
            :id="id"
            v-model="model"
            class="relative h-6 w-11 shrink-0 cursor-pointer rounded-full bg-stone-300 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 data-[state=checked]:bg-emerald-600"
        >
            <SwitchThumb
                class="block size-5 translate-x-0.5 rounded-full bg-white shadow transition-transform data-[state=checked]:translate-x-[1.375rem]"
            />
        </SwitchRoot>
    </div>
</template>
