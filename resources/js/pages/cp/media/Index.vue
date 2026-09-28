<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { destroy, store, update } from '@/routes/cp/media';
import type { Media } from '@/types';

defineOptions({ layout: CpLayout });

defineProps<{
    media: Media[];
}>();

const form = useForm<{ file: File | null }>({ file: null });

const upload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);

    // One request per file keeps each upload under PHP's size limits.
    for (const file of files) {
        form.file = file;
        await new Promise<void>((resolve) =>
            form.submit(store(), {
                preserveScroll: true,
                onFinish: () => resolve(),
            }),
        );
    }

    form.reset();
    input.value = '';
};

const saveAlt = (item: Media, alt: string) => {
    if (alt === (item.alt ?? '')) {
        return;
    }

    router.patch(update(item.id).url, { alt }, { preserveScroll: true });
};

const remove = (item: Media) => {
    if (
        !confirm(
            `Delete "${item.filename}"? Posts using it will no longer show it.`,
        )
    ) {
        return;
    }

    router.delete(destroy(item.id).url, { preserveScroll: true });
};

const formatSize = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.round(bytes / 1024)} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

const copyUrl = (item: Media) => navigator.clipboard.writeText(item.url);
</script>

<template>
    <Head title="Media" />
    <PageHeader title="Media">
        <label class="cp-btn-primary cursor-pointer">
            {{ form.processing ? 'Uploading…' : 'Upload images' }}
            <input
                type="file"
                multiple
                accept="image/jpeg,image/png,image/gif,image/webp,image/avif"
                class="sr-only"
                :disabled="form.processing"
                @change="upload"
            />
        </label>
    </PageHeader>

    <p v-if="form.errors.file" class="cp-error mb-4">{{ form.errors.file }}</p>

    <p v-if="media.length === 0" class="cp-card p-6 text-sm text-neutral-500">
        No images yet.
    </p>

    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <li
            v-for="item in media"
            :key="item.id"
            class="cp-card overflow-hidden"
        >
            <a :href="item.url" target="_blank">
                <img
                    :src="item.url"
                    :alt="item.alt ?? item.filename"
                    class="aspect-video w-full bg-neutral-100 object-cover dark:bg-neutral-800"
                    loading="lazy"
                />
            </a>
            <div class="space-y-2 p-3">
                <p class="truncate text-sm font-medium" :title="item.filename">
                    {{ item.filename }}
                </p>
                <p class="text-xs text-neutral-500">
                    <template v-if="item.width && item.height">
                        {{ item.width }}&times;{{ item.height }} &middot;
                    </template>
                    {{ formatSize(item.size) }}
                </p>
                <input
                    type="text"
                    :value="item.alt ?? ''"
                    placeholder="Alt text"
                    :aria-label="`Alt text for ${item.filename}`"
                    class="cp-input text-xs"
                    @change="
                        saveAlt(item, ($event.target as HTMLInputElement).value)
                    "
                />
                <div class="flex justify-between">
                    <button
                        type="button"
                        class="cp-btn text-xs"
                        @click="copyUrl(item)"
                    >
                        Copy URL
                    </button>
                    <button
                        type="button"
                        class="cp-btn-danger text-xs"
                        @click="remove(item)"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </li>
    </ul>
</template>
