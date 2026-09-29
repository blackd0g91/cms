<script setup lang="ts">
import { ref } from 'vue';
import MediaPicker from '@/components/cp/MediaPicker.vue';
import type { Media } from '@/types';

const props = defineProps<{
    id: string;
    initial?: Media | null;
}>();

const model = defineModel<number | null>();

const emit = defineEmits<{
    // The full image, for callers that show it elsewhere (like a preview).
    change: [media: Media | null];
}>();

const picker = ref<InstanceType<typeof MediaPicker>>();
const selected = ref<Media | null>(props.initial ?? null);

const select = (media: Media) => {
    selected.value = media;
    model.value = media.id;
    emit('change', media);
};

const clear = () => {
    selected.value = null;
    model.value = null;
    emit('change', null);
};
</script>

<template>
    <div class="flex items-start gap-4">
        <img
            v-if="selected"
            :src="selected.thumb_url ?? selected.url"
            :alt="selected.alt ?? selected.filename"
            class="h-24 w-24 rounded-md border border-neutral-200 object-cover dark:border-neutral-800"
        />
        <div
            v-else
            class="flex h-24 w-24 items-center justify-center rounded-md border border-dashed border-neutral-300 text-xs text-neutral-400 dark:border-neutral-700"
        >
            No image
        </div>
        <div class="flex flex-col gap-2">
            <button
                :id="id"
                type="button"
                class="cp-btn"
                @click="picker?.open()"
            >
                {{ selected ? 'Change image' : 'Choose image' }}
            </button>
            <button
                v-if="selected"
                type="button"
                class="cp-btn-danger"
                @click="clear"
            >
                Remove
            </button>
        </div>
        <MediaPicker ref="picker" @select="select" />
    </div>
</template>
