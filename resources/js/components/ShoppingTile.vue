<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import {
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuPortal,
    ContextMenuRoot,
    ContextMenuTrigger,
    Primitive,
} from 'reka-ui';
import { Ban, Check, Repeat, Trash2, Undo2 } from 'lucide-vue-next';
import { shoppingApi } from '@/api/planning';
import type { ShoppingListItem } from '@/api/types';
import { useShoppingAmounts } from '@/composables/useShoppingAmounts';
import { useStores } from '@/composables/useStores';
import { useToasts } from '@/composables/useToasts';
import ProductIcon from '@/components/ProductIcon.vue';
import ShoppingSubstituteDialog from '@/components/ShoppingSubstituteDialog.vue';
import ShoppingTileInfo from '@/components/ShoppingTileInfo.vue';

/**
 * Tile of the shopping list: a line icon, the product's name and a short
 * amount. Products bought as this one follow by name, so whoever shops
 * sees the nuance and decides what to take. Tapping it checks the product
 * off or brings it back. A long press or right click tells what exactly
 * to buy of each, which recipes need it and what remains, and offers to
 * skip the position or to note what was bought in its place.
 */
const props = defineProps<{ item: ShoppingListItem }>();

const MENU_ITEM_CLASS =
    'flex cursor-pointer items-center gap-2 rounded-field px-2 py-1.5 outline-none data-[highlighted]:bg-accent-soft';

const { t } = useI18n();
const { short } = useShoppingAmounts();
const { isActive } = useStores();
const { showError } = useToasts();
const queryClient = useQueryClient();

const isSubstituting = ref(false);
const isChecked = computed(() => props.item.checked_at !== null);
const isSkipped = computed(() => props.item.skipped_at !== null);
const isSettled = computed(() => isChecked.value || isSkipped.value);
const hasInfo = computed(
    () =>
        Boolean(props.item.product?.note) ||
        props.item.variants.length > 0 ||
        (props.item.product?.offers ?? []).some(isActive),
);
const baseUnit = computed(() => props.item.product?.base_unit ?? 'g');
const name = computed(() => props.item.product?.name ?? props.item.label ?? '');
const hasOwnAmounts = computed(
    () =>
        props.item.purchase_amount !== null ||
        props.item.needed_amount !== null ||
        props.item.extra_amounts.length > 0,
);

/**
 * The amount in as few characters as possible, for the tile. Without an
 * amount of its own, the tile names its variants with theirs instead.
 */
const shortAmount = computed(() =>
    hasOwnAmounts.value
        ? short(props.item, baseUnit.value)
        : props.item.variants.map((variant) => short(variant, variant.base_unit)).join(' + '),
);

const variantNames = computed(() => props.item.variants.map((variant) => variant.name).join(', '));

/**
 * What a settled tile says below the name: what was bought instead, or
 * that it is not bought.
 */
const outcome = computed(() => {
    if (isSkipped.value) {
        return t('shopping.skipped');
    }

    return props.item.substitute ? t('shopping.substituted', { name: props.item.substitute.name }) : null;
});

function refresh(): void {
    queryClient.invalidateQueries({ queryKey: ['shopping-list'] });
}

const toggle = useMutation({
    mutationFn: () =>
        isSkipped.value
            ? shoppingApi.setSkipped(props.item.id, false)
            : shoppingApi.setChecked(props.item.id, !isChecked.value),
    onSuccess: refresh,
    onError: showError,
});

const skip = useMutation({
    mutationFn: () => shoppingApi.setSkipped(props.item.id, true),
    onSuccess: refresh,
    onError: showError,
});

const remove = useMutation({
    mutationFn: () => shoppingApi.removeItem(props.item.id),
    onSuccess: refresh,
    onError: showError,
});
</script>

<template>
    <li>
        <ContextMenuRoot>
            <ContextMenuTrigger as-child>
                <Primitive
                    as="button"
                    type="button"
                    :aria-pressed="isSettled"
                    class="relative flex aspect-square w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-tile border p-1.5 text-center transition-colors select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    :class="
                        isSettled
                            ? 'border-line bg-sunken text-done-ink hover:bg-line'
                            : 'border-transparent bg-accent text-on-accent hover:bg-accent-hover'
                    "
                    @click="toggle.mutate()"
                >
                    <span
                        v-if="hasInfo"
                        class="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-current opacity-80"
                    />
                    <Ban v-if="isSkipped" class="size-9 text-done-mark" aria-hidden="true" />
                    <Check v-else-if="isChecked" class="size-9 text-done-mark" aria-hidden="true" />
                    <ProductIcon
                        v-else
                        :icon="item.product?.icon"
                        :section-key="item.product?.store_section?.key"
                        class="size-9"
                    />
                    <span
                        class="line-clamp-1 w-full text-xs leading-tight font-semibold"
                        :class="{ 'line-through': isSettled }"
                        >{{ name }}</span
                    >
                    <span v-if="outcome" class="w-full truncate text-[10px] leading-none font-medium">{{
                        outcome
                    }}</span>
                    <span
                        v-else-if="shortAmount"
                        class="w-full truncate text-[10px] leading-none font-medium"
                    >
                        <template v-if="item.is_approximate">≈ </template>{{ shortAmount }}
                    </span>
                    <span
                        v-if="variantNames && hasOwnAmounts"
                        class="w-full truncate text-[10px] leading-none font-medium opacity-80"
                        >+ {{ variantNames }}</span
                    >
                </Primitive>
            </ContextMenuTrigger>
            <ContextMenuPortal>
                <ContextMenuContent
                    class="z-50 w-64 rounded-tile border border-line bg-surface p-3 text-sm text-ink shadow-lg"
                >
                    <p class="font-semibold text-ink">{{ name }}</p>
                    <ShoppingTileInfo :item="item" :has-own-amounts="hasOwnAmounts" />
                    <div class="mt-2 border-t border-line pt-2">
                        <ContextMenuItem v-if="isSettled" :class="MENU_ITEM_CLASS" @select="toggle.mutate()">
                            <Undo2 class="size-4" />{{ t(isSkipped ? 'shopping.unskip' : 'shopping.reopen') }}
                        </ContextMenuItem>
                        <template v-else>
                            <ContextMenuItem :class="MENU_ITEM_CLASS" @select="isSubstituting = true">
                                <Repeat class="size-4" />{{ t('shopping.substitute') }}
                            </ContextMenuItem>
                            <ContextMenuItem
                                v-if="!item.is_manual"
                                :class="MENU_ITEM_CLASS"
                                @select="skip.mutate()"
                            >
                                <Ban class="size-4" />{{ t('shopping.skip') }}
                            </ContextMenuItem>
                        </template>
                    </div>
                    <ContextMenuItem
                        v-if="item.is_manual"
                        class="mt-1 flex cursor-pointer items-center gap-2 rounded-field px-2 py-1.5 text-danger-deep outline-none data-[highlighted]:bg-danger-soft"
                        @select="remove.mutate()"
                    >
                        <Trash2 class="size-4" />{{ t('common.delete') }}
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenuPortal>
        </ContextMenuRoot>
        <ShoppingSubstituteDialog v-model:open="isSubstituting" :item="item" :name="name" />
    </li>
</template>
