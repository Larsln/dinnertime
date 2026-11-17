<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { Check, Merge, Plus, Trash2 } from 'lucide-vue-next';
import { productsApi } from '@/api/products';
import type { BaseUnit, Product } from '@/api/types';
import { useReferenceData } from '@/composables/useReferenceData';
import { useToasts } from '@/composables/useToasts';
import ProductPicker from '@/components/ProductPicker.vue';
import AppButton from '@/components/ui/AppButton.vue';
import CheckboxField from '@/components/ui/CheckboxField.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import NumberField from '@/components/ui/NumberField.vue';
import SelectField from '@/components/ui/SelectField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import TextField from '@/components/ui/TextField.vue';

/**
 * Editor for a product with its packages and unit conversions.
 *
 * Suggested values can be corrected and are confirmed together with one
 * tap. Duplicates can be merged into another product.
 */
interface PackageRow {
    id: number | null;
    label: string;
    amount: number | null;
}

interface ConversionRow {
    unitId: number;
    unitName: string;
    amount: number | null;
}

const props = defineProps<{ product: Product }>();

const BASE_UNITS: BaseUnit[] = ['g', 'ml', 'piece'];

const { t } = useI18n();
const queryClient = useQueryClient();
const { showError, showSuccess } = useToasts();
const { storeSections, units } = useReferenceData();

const details = reactive({
    name: '',
    storeSectionId: null as number | null,
    baseUnit: 'g' as BaseUnit | null,
    isPantryStaple: false,
    shelfLife: null as number | null,
});

const packages = ref<PackageRow[]>([]);
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
            storeSectionId: product.store_section?.id ?? null,
            baseUnit: product.base_unit,
            isPantryStaple: product.is_pantry_staple,
            shelfLife: product.shelf_life_opened_days,
        });

        packages.value = (product.packages ?? []).map((entry) => ({
            id: entry.id,
            label: entry.label,
            amount: entry.amount_in_base,
        }));

        conversions.value = (product.conversions ?? []).map((entry) => ({
            unitId: entry.unit.id,
            unitName: entry.unit.name,
            amount: entry.amount_in_base,
        }));
    },
    { immediate: true },
);

const sectionOptions = computed(() =>
    storeSections.value.map((section) => ({ value: section.id, label: section.name })),
);
const baseUnitOptions = computed(() =>
    BASE_UNITS.map((value) => ({ value, label: t(`baseUnits.${value}`) })),
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
        (props.product.packages ?? []).some((entry) => entry.status === 'suggested') ||
        (props.product.conversions ?? []).some((entry) => entry.status === 'suggested'),
);

function addConversion(): void {
    const unit = units.value.find((candidate) => candidate.id === newUnitId.value);

    if (unit) {
        conversions.value.push({ unitId: unit.id, unitName: unit.name, amount: null });
        newUnitId.value = null;
    }
}

async function savePackages(): Promise<void> {
    const original = props.product.packages ?? [];
    const kept = new Set(packages.value.map((row) => row.id));

    for (const entry of original.filter((candidate) => !kept.has(candidate.id))) {
        await productsApi.removePackage(entry.id);
    }

    for (const row of packages.value.filter(
        (candidate) => candidate.amount && candidate.label.trim() !== '',
    )) {
        const before = original.find((candidate) => candidate.id === row.id);

        if (row.id === null) {
            await productsApi.addPackage(props.product.id, row.label.trim(), row.amount ?? 0);
        } else if (before?.label !== row.label || before.amount_in_base !== row.amount) {
            await productsApi.updatePackage(row.id, row.label.trim(), row.amount ?? 0);
        }
    }
}

async function saveConversions(): Promise<void> {
    const original = props.product.conversions ?? [];
    const kept = new Set(conversions.value.map((row) => row.unitId));

    for (const entry of original.filter((candidate) => !kept.has(candidate.unit.id))) {
        await productsApi.removeConversion(props.product.id, entry.unit.id);
    }

    for (const row of conversions.value.filter((candidate) => candidate.amount)) {
        const before = original.find((candidate) => candidate.unit.id === row.unitId);

        if (before?.amount_in_base !== row.amount) {
            await productsApi.setConversion(props.product.id, row.unitId, row.amount ?? 0);
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
            store_section_id: details.storeSectionId,
            base_unit: details.baseUnit ?? 'g',
            is_pantry_staple: details.isPantryStaple,
            shelf_life_opened_days: details.shelfLife,
        });
        await savePackages();
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
    <article class="rounded-2xl border border-stone-200 bg-white p-4">
        <header class="mb-4 flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold">{{ product.name }}</h2>
            <StatusBadge v-if="product.status === 'suggested'" tone="warning">{{
                t('products.suggested')
            }}</StatusBadge>
            <StatusBadge v-if="product.enriched_at === null" tone="info">{{
                t('products.notEnriched')
            }}</StatusBadge>
            <span v-if="product.recipe_count !== undefined" class="text-sm text-stone-500">
                {{ t('products.recipeCount', { count: product.recipe_count }) }}
            </span>
            <span v-if="product.aliases?.length" class="text-sm text-stone-500"
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

        <div class="mt-5 grid gap-6 lg:grid-cols-2">
            <section>
                <FieldLabel>{{ t('products.packages', { unit: baseUnitLabel }) }}</FieldLabel>
                <div v-for="(row, index) in packages" :key="index" class="mb-2 flex gap-2">
                    <TextField v-model="row.label" class="flex-1" :placeholder="t('products.packageLabel')" />
                    <NumberField v-model="row.amount" class="w-28" :placeholder="baseUnitLabel" />
                    <AppButton
                        size="icon"
                        variant="ghost"
                        :aria-label="t('common.delete')"
                        @click="packages.splice(index, 1)"
                    >
                        <Trash2 class="size-4" />
                    </AppButton>
                </div>
                <AppButton size="sm" @click="packages.push({ id: null, label: '', amount: null })">
                    <Plus class="size-4" />{{ t('products.addPackage') }}
                </AppButton>
            </section>

            <section>
                <FieldLabel>{{ t('products.conversions', { unit: baseUnitLabel }) }}</FieldLabel>
                <div
                    v-for="(row, index) in conversions"
                    :key="row.unitId"
                    class="mb-2 flex items-center gap-2"
                >
                    <span class="flex-1 text-sm text-stone-700">1 {{ row.unitName }} =</span>
                    <NumberField v-model="row.amount" class="w-28" :placeholder="baseUnitLabel" />
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
