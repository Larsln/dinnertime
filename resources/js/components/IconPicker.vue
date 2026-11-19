<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
    ToggleGroupItem,
    ToggleGroupRoot,
} from 'reka-ui';
import { PRODUCT_ICONS, type ProductIconEntry } from '@/lib/productIcons';
import ProductIcon from '@/components/ProductIcon.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Chooses the line icon of a product from the searchable set. Without a
 * choice the product shows the generic icon of its store section.
 */
const model = defineModel<string | null>({ required: true });

defineProps<{ label: string; sectionKey?: string | null }>();

const { t, locale } = useI18n();

const isOpen = ref(false);
const search = ref('');

const selected = computed({
    get: () => model.value ?? undefined,
    set: (key: string | undefined) => {
        model.value = key || null;
        isOpen.value = false;
    },
});

const matches = computed(() => {
    const wanted = search.value.trim().toLowerCase();

    return PRODUCT_ICONS.filter((entry) => nameOf(entry).toLowerCase().includes(wanted));
});

function nameOf(entry: ProductIconEntry): string {
    return locale.value === 'de' ? entry.de : entry.en;
}

function clear(): void {
    model.value = null;
    isOpen.value = false;
}
</script>

<template>
    <div>
        <FieldLabel>{{ label }}</FieldLabel>
        <PopoverRoot v-model:open="isOpen">
            <PopoverTrigger as-child>
                <AppButton>
                    <ProductIcon :icon="model" :section-key="sectionKey" class="size-6" />
                    {{ t('products.changeIcon') }}
                </AppButton>
            </PopoverTrigger>
            <PopoverPortal>
                <PopoverContent
                    align="start"
                    :side-offset="6"
                    class="z-50 w-80 rounded-tile border border-line bg-surface p-3 shadow-lg"
                >
                    <TextField v-model="search" :label="t('common.search')" />
                    <ToggleGroupRoot
                        v-model="selected"
                        type="single"
                        class="mt-2 grid max-h-64 grid-cols-7 gap-1 overflow-y-auto"
                    >
                        <ToggleGroupItem
                            v-for="entry in matches"
                            :key="entry.key"
                            :value="entry.key"
                            :title="nameOf(entry)"
                            :aria-label="nameOf(entry)"
                            class="flex aspect-square cursor-pointer items-center justify-center rounded-field border border-transparent text-ink-muted hover:bg-sunken focus-visible:outline-2 focus-visible:outline-accent data-[state=on]:border-accent data-[state=on]:bg-accent-soft data-[state=on]:text-accent-deep"
                        >
                            <ProductIcon :icon="entry.key" class="size-6" />
                        </ToggleGroupItem>
                    </ToggleGroupRoot>
                    <p v-if="matches.length === 0" class="mt-2 text-sm text-ink-muted">
                        {{ t('products.noIcon') }}
                    </p>
                    <AppButton v-if="model" variant="ghost" class="mt-2" @click="clear">
                        {{ t('products.sectionIcon') }}
                    </AppButton>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </div>
</template>
