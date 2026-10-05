<script setup lang="ts">
import { computed, ref } from 'vue';
import MediaPicker from '@/components/cp/MediaPicker.vue';
import type { Media } from '@/types';

const props = defineProps<{
    id: string;
    // Images already in the gallery, by id, for their previews.
    initial?: Record<number, Media>;
}>();

// Image ids, in the order they are shown.
const model = defineModel<number[] | null>();

const picker = ref<InstanceType<typeof MediaPicker>>();
const known = ref<Record<number, Media>>({ ...props.initial });

const ids = computed(() => model.value ?? []);

// Picking an image adds it at the end, and picking it again takes it out.
const toggle = (media: Media) => {
    known.value[media.id] = media;
    model.value = ids.value.includes(media.id)
        ? ids.value.filter((id) => id !== media.id)
        : [...ids.value, media.id];
};

const move = (index: number, by: number) => {
    const items = [...ids.value];
    [items[index], items[index + by]] = [items[index + by], items[index]];
    model.value = items;
};

const remove = (index: number) => {
    model.value = ids.value.filter((_, i) => i !== index);
};
</script>

<template>
    <div class="space-y-3">
        <ol v-if="ids.length" class="flex flex-wrap gap-3">
            <li
                v-for="(mediaId, i) in ids"
                :key="mediaId"
                class="w-24 space-y-1.5"
            >
                <img
                    v-if="known[mediaId]"
                    :src="known[mediaId].thumb_url ?? known[mediaId].url"
                    :alt="known[mediaId].alt ?? known[mediaId].filename"
                    class="size-24 rounded-md border border-neutral-200 object-cover dark:border-neutral-800"
                />
                <div
                    v-else
                    class="flex size-24 items-center justify-center rounded-md border border-dashed border-neutral-300 text-xs text-neutral-400 dark:border-neutral-700"
                >
                    Image #{{ mediaId }}
                </div>
                <div class="flex gap-1">
                    <button
                        type="button"
                        class="cp-btn flex-1 px-0 py-1"
                        :disabled="i === 0"
                        aria-label="Move left"
                        @click="move(i, -1)"
                    >
                        &larr;
                    </button>
                    <button
                        type="button"
                        class="cp-btn flex-1 px-0 py-1"
                        :disabled="i === ids.length - 1"
                        aria-label="Move right"
                        @click="move(i, 1)"
                    >
                        &rarr;
                    </button>
                    <button
                        type="button"
                        class="cp-btn-danger flex-1 px-0 py-1"
                        aria-label="Remove"
                        @click="remove(i)"
                    >
                        &times;
                    </button>
                </div>
            </li>
        </ol>
        <button :id="id" type="button" class="cp-btn" @click="picker?.open()">
            {{ ids.length ? 'Add or remove images' : 'Choose images' }}
        </button>
        <MediaPicker ref="picker" multiple :selected="ids" @select="toggle" />
    </div>
</template>
