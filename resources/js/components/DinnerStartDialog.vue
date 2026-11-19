<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDinnerRound } from '@/composables/useDinnerRound';
import AppButton from '@/components/ui/AppButton.vue';
import DialogPanel from '@/components/ui/DialogPanel.vue';
import SegmentedField from '@/components/ui/SegmentedField.vue';
import SwitchField from '@/components/ui/SwitchField.vue';

/**
 * Asks how tonight's dinner is decided and starts the round.
 */
const QUICK_MINUTES = 30;

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ started: [] }>();

const { t } = useI18n();
const { start } = useDinnerRound();

const onlyAvailable = ref(false);
const isQuick = ref(false);
const party = ref<'together' | 'solo'>('together');

const partyOptions = computed(() => [
    { value: 'together' as const, label: t('dinner.together') },
    { value: 'solo' as const, label: t('dinner.solo') },
]);

watch(open, (isOpen) => {
    if (isOpen) {
        onlyAvailable.value = false;
        isQuick.value = false;
        party.value = 'together';
    }
});

function begin(): void {
    start.mutate(
        {
            is_solo: party.value === 'solo',
            only_available: onlyAvailable.value,
            max_minutes: isQuick.value ? QUICK_MINUTES : null,
        },
        {
            onSuccess: () => {
                open.value = false;
                emit('started');
            },
        },
    );
}
</script>

<template>
    <DialogPanel
        v-model:open="open"
        :title="t('dinner.title')"
        :description="t('dinner.startHint')"
        :close-label="t('common.close')"
    >
        <SegmentedField v-model="party" :label="t('dinner.who')" :options="partyOptions" />
        <div class="divide-y divide-line">
            <SwitchField v-model="onlyAvailable" :label="t('dinner.onlyAvailable')" />
            <SwitchField v-model="isQuick" :label="t('dinner.quick', { minutes: QUICK_MINUTES })" />
        </div>
        <template #actions>
            <AppButton variant="primary" :disabled="start.isPending.value" @click="begin">
                {{ t('dinner.begin') }}
            </AppButton>
        </template>
    </DialogPanel>
</template>
