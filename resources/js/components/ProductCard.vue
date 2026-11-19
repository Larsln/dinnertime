<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { Check, Merge, Plus, Trash2 } from 'lucide-vue-next';
import { productsApi } from '@/api/products';
import type { BaseUnit, Product } from '@/api/types';
import { useReferenceData } from '@/composables/useReferenceData';
import { useToasts } from '@/composables/useToasts';
import IconPicker from '@/components/IconPicker.vue';
import ProductOffers from '@/components/ProductOffers.vue';
import ProductPicker from '@/components/ProductPicker.vue';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import NumberField from '@/components/ui/NumberField.vue';
import SelectField from '@/components/ui/SelectField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Editor for a product with its package sizes and unit conversions.
 *
 * Suggested values can be corrected and are confirmed together with one
 * tap. A unit can be marked as not convertible, for products whose single
 * items vary too much. Duplicates can be merged into another product.
 */
interface ConversionRow {
    unitId: number;
    unitName: string;
    amount: number | null;
    isConvertible: boolean;
}

const props = defineProps<{ product: Product }>();

const BASE_UNITS: BaseUnit[] = ['g', 'ml', 'piece'];

const { t } = useI18n();
const queryClient = useQueryClient();
const { showError, showSuccess } = useToasts();
const { storeSections, units } = useReferenceData();

const details = reactive({
    name: '',
    icon: null as string | null,
    storeSectionId: null as number | null,
    baseUnit: 'g' as BaseUnit | null,
    isPantryStaple: false,
    shelfLife: null as number | null,
    note: '',
});

const conversions = ref<ConversionRow[]>([]);
const newUnitId = ref<number | null>(null);
const isMerging = ref(false);
const isDeleting = ref(false);
const mergeTarget = ref<Product | null>(null);

watch(
    () => props.product,
    (product) => {
        Object.assign(details, {
            name: product.name,
            icon: product.icon,
            storeSectionId: product.store_section?.id ?? null,
            baseUnit: product.base_unit,
            isPantryStaple: product.is_pantry_staple,
            shelfLife: product.shelf_life_opened_days,
            note: product.note ?? '',
        });

        conversions.value = (product.conversions ?? []).map((entry) => ({
            unitId: entry.unit.id,
            unitName: entry.unit.name,
            amount: entry.amount_in_base,
            isConvertible: entry.amount_in_base !== null,
        }));
    },
    { immediate: true },
);

const sectionOptions = computed(() =>
    storeSections.value.map((section) => ({ value: section.id, label: section.name })),
);
const baseUnitOptions = computed(() =>
    BASE_UNITS.map((value) => ({ value, label: t(`dimensions.${value}`) })),
);
const baseUnitLabel = computed(() => t(`baseUnits.${details.baseUnit ?? 'g'}`));

const addableUnits = computed(() =>
    units.value
        .filter((unit) => !conversions.value.some((row) => row.unitId === unit.id))
        .map((unit) => ({ value: unit.id, label: unit.name })),
);

const hasSuggestions = computed(
    () =>
        props.product.status === 'suggested' ||
        (props.product.conversions ?? []).some((entry) => entry.status === 'suggested'),
);

function addConversion(): void {
    const unit = units.value.find((candidate) => candidate.id === newUnitId.value);

    if (unit) {
        conversions.value.push({ unitId: unit.id, unitName: unit.name, amount: null, isConvertible: true });
        newUnitId.value = null;
    }
}

async function saveConversions(): Promise<void> {
    const original = props.product.conversions ?? [];
    const kept = new Set(conversions.value.map((row) => row.unitId));

    for (const entry of original.filter((candidate) => !kept.has(candidate.unit.id))) {
        await productsApi.removeConversion(props.product.id, entry.unit.id);
    }

    for (const row of conversions.value.filter((candidate) => candidate.amount || !candidate.isConvertible)) {
        const before = original.find((candidate) => candidate.unit.id === row.unitId);
        const amount = row.isConvertible ? row.amount : null;

        if (before === undefined || before.amount_in_base !== amount) {
            await productsApi.setConversion(props.product.id, row.unitId, amount);
        }
    }
}

function refresh(): void {
    queryClient.invalidateQueries({ queryKey: ['review'] });
    queryClient.invalidateQueries({ queryKey: ['products'] });
    queryClient.invalidateQueries({ queryKey: ['product'] });
}

const save = useMutation({
    mutationFn: async (confirm: boolean) => {
        await productsApi.update(props.product.id, {
            name: details.name,
            icon: details.icon,
            store_section_id: details.storeSectionId,
            base_unit: details.baseUnit ?? 'g',
            is_pantry_staple: details.isPantryStaple,
            shelf_life_opened_days: details.shelfLife,
            note: details.note.trim() || null,
        });
        await saveConversions();

        if (confirm) {
            await productsApi.confirm(props.product.id);
        }
    },
    onSuccess: () => {
        refresh();
        showSuccess(t('products.saved'));
    },
    onError: (error) => {
        refresh();
        showError(error);
    },
});

const merge = useMutation({
    mutationFn: () => productsApi.merge(props.product.id, mergeTarget.value?.id ?? 0),
    onSuccess: () => {
        isMerging.value = false;
        refresh();
    },
    onError: showError,
});

const remove = useMutation({
    mutationFn: () => productsApi.remove(props.product.id),
    onSuccess: refresh,
    onError: showError,
});
</script>

<template>
    <article class="rounded-card border border-line bg-surface p-4">
        <header class="mb-4 flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-display font-bold">{{ product.name }}</h2>
            <StatusBadge v-if="product.status === 'suggested'" tone="warning">{{
                t('products.suggested')
            }}</StatusBadge>
            <StatusBadge v-if="product.enriched_at === null" tone="info">{{
                t('products.notEnriched')
            }}</StatusBadge>
            <span v-if="product.recipe_count !== undefined" class="text-sm text-ink-muted">
                {{ t('products.recipeCount', { count: product.recipe_count }) }}
            </span>
            <span v-if="product.aliases?.length" class="text-sm text-ink-muted"
                >· {{ product.aliases.join(', ') }}</span
            >
        </header>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <TextField v-model="details.name" :label="t('products.name')" />
            <SelectField
                v-model="details.storeSectionId"
                :options="sectionOptions"
                :label="t('products.section')"
                :placeholder="t('products.noSection')"
                :empty-label="t('products.noSection')"
            />
            <SelectField
                v-model="details.baseUnit"
                :options="baseUnitOptions"
                :label="t('products.baseUnit')"
            />
            <NumberField
                v-model="details.shelfLife"
                :label="t('products.shelfLife')"
                :min="1"
                :placeholder="t('products.shelfLifeUnlimited')"
            />
            <CheckboxField
                v-model="details.isPantryStaple"
                class="sm:col-span-2 lg:col-span-4"
                :label="t('products.pantryStaple')"
            />
        </div>

        <TextArea v-model="details.note" class="mt-4" :rows="2" :label="t('products.note')" />

        <IconPicker
            v-model="details.icon"
            class="mt-4"
            :label="t('products.icon')"
            :section-key="product.store_section?.key"
        />

        <div class="mt-5 grid gap-6 lg:grid-cols-2">
            <ProductOffers :product="product" />

            <section>
                <FieldLabel>{{ t('products.conversions', { unit: baseUnitLabel }) }}</FieldLabel>
                <div
                    v-for="(row, index) in conversions"
                    :key="row.unitId"
                    class="mb-2 flex items-center gap-2"
                >
                    <span class="flex-1 text-sm text-ink">1 {{ row.unitName }} =</span>
                    <NumberField
                        v-if="row.isConvertible"
                        v-model="row.amount"
                        class="w-28"
                        :placeholder="baseUnitLabel"
                    />
                    <CheckboxField
                        :model-value="!row.isConvertible"
                        :label="t('products.notConvertible')"
                        @update:model-value="(checked) => (row.isConvertible = !checked)"
                    />
                    <AppButton
                        size="icon"
                        variant="ghost"
                        :aria-label="t('common.delete')"
                        @click="conversions.splice(index, 1)"
                    >
                        <Trash2 class="size-4" />
                    </AppButton>
                </div>
                <div class="flex gap-2">
                    <SelectField
                        v-model="newUnitId"
                        class="flex-1"
                        :options="addableUnits"
                        :placeholder="t('products.addConversion')"
                    />
                    <AppButton
                        size="icon"
                        :aria-label="t('common.add')"
                        :disabled="newUnitId === null"
                        @click="addConversion"
                    >
                        <Plus class="size-4" />
                    </AppButton>
                </div>
            </section>
        </div>

        <footer class="mt-5 flex flex-wrap justify-end gap-2">
            <AppButton variant="ghost" @click="isDeleting = true"
                ><Trash2 class="size-4" />{{ t('common.delete') }}</AppButton
            >
            <AppButton variant="ghost" @click="isMerging = true"
                ><Merge class="size-4" />{{ t('products.merge') }}</AppButton
            >
            <AppButton :disabled="save.isPending.value" @click="save.mutate(false)">{{
                t('common.save')
            }}</AppButton>
            <AppButton
                v-if="hasSuggestions"
                variant="primary"
                :disabled="save.isPending.value"
                @click="save.mutate(true)"
            >
                <Check class="size-4" />{{ t('products.confirm') }}
            </AppButton>
        </footer>

        <DialogPanel
            v-model:open="isMerging"
            :title="t('products.merge')"
            :description="t('products.mergeDescription', { name: product.name })"
            :close-label="t('common.close')"
        >
            <ProductPicker v-model="mergeTarget" :label="t('products.mergeInto')" />
            <template #actions>
                <AppButton
                    variant="danger"
                    :disabled="mergeTarget === null || mergeTarget.id === product.id || merge.isPending.value"
                    @click="merge.mutate()"
                >
                    {{ t('products.merge') }}
                </AppButton>
            </template>
        </DialogPanel>

        <ConfirmDialog
            v-model:open="isDeleting"
            :title="t('products.deleteTitle')"
            :description="t('products.deleteDescription', { name: product.name })"
            :confirm-label="t('common.delete')"
            :cancel-label="t('common.cancel')"
            @confirm="remove.mutate()"
        />
    </article>
</template>
