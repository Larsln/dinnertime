<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { HouseholdMember } from '@/api/settings';
import ChipGroup from '@/components/ui/ChipGroup.vue';
import FieldLabel from '@/components/ui/FieldLabel.vue';
import NumberField from '@/components/ui/NumberField.vue';

/**
 * Who eats a meal: the household members who join and a number of
 * guests. Members get the portion that suits them, guests a standard one.
 */
const eaterIds = defineModel<number[]>('eaterIds', { required: true });
const guests = defineModel<number | null>('guests', { required: true });

const props = defineProps<{ members: HouseholdMember[] }>();

const { t } = useI18n();

const options = computed(() => props.members.map((member) => ({ value: member.id, label: member.name })));
</script>

<template>
    <div class="space-y-3">
        <div v-if="members.length > 0">
            <FieldLabel>{{ t('plan.eaters') }}</FieldLabel>
            <ChipGroup v-model="eaterIds" :options="options" />
        </div>
        <NumberField v-model="guests" :label="t('plan.guests')" :min="0" :max="50" steppers />
        <p class="text-sm text-ink-muted">{{ t('plan.eatersHint') }}</p>
    </div>
</template>
