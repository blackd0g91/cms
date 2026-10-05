<script setup lang="ts">
import { ref } from 'vue';
import { requestJson } from '@/lib/http';
import { IMAGE_TYPES, uploadImage } from '@/lib/media';
import { cn } from '@/lib/utils';
import { library } from '@/routes/cp/media';
import type { Media } from '@/types';

const props = defineProps<{
    // Stays open to pick several, one click each, with the ones already
    // picked (selected) checked. Picking one of those again unpicks it.
    multiple?: boolean;
    selected?: number[];
}>();

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

    if (!props.multiple) {
        close();
    }
};

const isSelected = (item: Media) => props.selected?.includes(item.id) ?? false;

const upload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = [...(input.files ?? [])];

    if (files.length === 0) {
        return;
    }

    uploading.value = true;
    error.value = null;

    try {
        for (const file of files) {
            const item = await uploadImage(file);

            // New uploads are first in the library too.
            media.value.unshift(item);
            choose(item);
        }
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
            <h2 class="font-semibold">
                {{ multiple ? 'Choose images' : 'Choose an image' }}
            </h2>
            <div class="flex items-center gap-2">
                <label class="cp-btn-primary cursor-pointer">
                    {{ uploading ? 'Uploading…' : 'Upload' }}
                    <input
                        type="file"
                        :accept="IMAGE_TYPES.join(',')"
                        :multiple="multiple"
                        class="sr-only"
                        :disabled="uploading"
                        @change="upload"
                    />
                </label>
                <button type="button" class="cp-btn" @click="close">
                    {{ multiple ? 'Done' : 'Close' }}
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
                        :class="
                            cn(
                                'relative block w-full overflow-hidden rounded-md border border-neutral-200 hover:ring-2 hover:ring-neutral-400 focus:ring-2 focus:ring-neutral-400 focus:outline-none dark:border-neutral-800',
                                isSelected(item) &&
                                    'ring-2 ring-brand hover:ring-brand focus:ring-brand',
                            )
                        "
                        :title="item.filename"
                        :aria-pressed="multiple ? isSelected(item) : undefined"
                        @click="choose(item)"
                    >
                        <img
                            :src="item.thumb_url ?? item.url"
                            :alt="item.alt ?? item.filename"
                            class="aspect-square w-full object-cover"
                            loading="lazy"
                        />
                        <span
                            v-if="isSelected(item)"
                            class="absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-brand text-sm text-brand-fg shadow"
                            aria-hidden="true"
                        >
                            &check;
                        </span>
                    </button>
                </li>
            </ul>
        </div>
    </dialog>
</template>
