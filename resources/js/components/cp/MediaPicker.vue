<script setup lang="ts">
import { ref } from 'vue';
import { requestJson } from '@/lib/http';
import { library, store } from '@/routes/cp/media';
import type { Media } from '@/types';

const emit = defineEmits<{
    select: [media: Media];
}>();

const dialog = ref<HTMLDialogElement>();
const media = ref<Media[]>([]);
const loading = ref(false);
const uploading = ref(false);
const error = ref<string | null>(null);

const open = async () => {
    dialog.value?.showModal();
    loading.value = true;
    error.value = null;

    try {
        media.value = (
            await requestJson<{ media: Media[] }>(library().url)
        ).media;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Could not load images';
    } finally {
        loading.value = false;
    }
};

const close = () => dialog.value?.close();

const choose = (item: Media) => {
    emit('select', item);
    close();
};

const upload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    const body = new FormData();
    body.append('file', file);

    uploading.value = true;
    error.value = null;

    try {
        const uploaded = (
            await requestJson<{ media: Media }>(store().url, {
                method: 'POST',
                body,
            })
        ).media;

        choose(uploaded);
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Upload failed';
    } finally {
        uploading.value = false;
        input.value = '';
    }
};

defineExpose({ open });
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto w-full max-w-3xl rounded-lg border border-neutral-200 bg-white p-0 text-neutral-900 backdrop:bg-black/40 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100"
        @click.self="close"
    >
        <div
            class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Choose an image</h2>
            <div class="flex items-center gap-2">
                <label class="cp-btn-primary cursor-pointer">
                    {{ uploading ? 'Uploading…' : 'Upload' }}
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp,image/avif"
                        class="sr-only"
                        :disabled="uploading"
                        @change="upload"
                    />
                </label>
                <button type="button" class="cp-btn" @click="close">
                    Close
                </button>
            </div>
        </div>

        <div class="max-h-[60vh] overflow-y-auto p-4">
            <p v-if="error" class="cp-error mb-3">{{ error }}</p>
            <p v-if="loading" class="text-sm text-neutral-500">Loading…</p>
            <p v-else-if="media.length === 0" class="text-sm text-neutral-500">
                No images yet. Upload one to get started.
            </p>
            <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <li v-for="item in media" :key="item.id">
                    <button
                        type="button"
                        class="block w-full overflow-hidden rounded-md border border-neutral-200 hover:ring-2 hover:ring-neutral-400 focus:ring-2 focus:ring-neutral-400 focus:outline-none dark:border-neutral-800"
                        :title="item.filename"
                        @click="choose(item)"
                    >
                        <img
                            :src="item.url"
                            :alt="item.alt ?? item.filename"
                            class="aspect-square w-full object-cover"
                            loading="lazy"
                        />
                    </button>
                </li>
            </ul>
        </div>
    </dialog>
</template>
