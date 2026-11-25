<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import DialogPanel from '@/components/ui/DialogPanel.vue';

/**
 * Plays the copy of the video a recipe was imported from, which the
 * server keeps because platforms take videos down.
 */
const open = defineModel<boolean>('open', { required: true });

defineProps<{ title: string; url: string }>();

const { t } = useI18n();
</script>

<template>
    <DialogPanel v-model:open="open" :title="title" :close-label="t('common.close')" size="lg">
        <video
            v-if="open"
            :src="url"
            controls
            autoplay
            playsinline
            class="max-h-[70dvh] w-full rounded-field bg-black"
        />
    </DialogPanel>
</template>
