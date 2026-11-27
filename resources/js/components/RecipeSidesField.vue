<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { SideKindKey } from '@/api/types';
import { useReferenceData } from '@/composables/useReferenceData';
import ChipGroup from '@/components/ui/ChipGroup.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import SelectField from '@/components/ui/SelectField.vue';

/**
 * What a recipe has to do with sides: either it is a side of one kind,
 * or it is a dish and names the kinds of side it is served with. A dish
 * that names none is planned without sides.
 */
const kind = defineModel<SideKindKey | null>('kind', { required: true });
const sideOptions = defineModel<SideKindKey[]>('options', { required: true });

const { t } = useI18n();
const { sideKinds } = useReferenceData();

const kinds = computed(() => sideKinds.value.map((entry) => ({ value: entry.key, label: entry.name })));
</script>

<template>
    <div class="space-y-4">
        <SelectField
            v-model="kind"
            :options="kinds"
            :label="t('recipes.sides.kind')"
            :placeholder="t('recipes.sides.noKind')"
            :empty-label="t('recipes.sides.noKind')"
        />
        <div v-if="kind === null">
            <FieldLabel>{{ t('recipes.sides.options') }}</FieldLabel>
            <ChipGroup v-model="sideOptions" :options="kinds" />
            <p class="mt-2 text-sm text-ink-muted">{{ t('recipes.sides.optionsHint') }}</p>
        </div>
    </div>
</template>
