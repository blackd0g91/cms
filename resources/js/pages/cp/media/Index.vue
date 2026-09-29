<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { destroy, store, update } from '@/routes/cp/media';
import { edit as editPost } from '@/routes/cp/templates/posts';
import type { Media, MediaUsage } from '@/types';

defineOptions({ layout: CpLayout });

const props = defineProps<{
    media: Media[];
    usages: Record<number, MediaUsage[]>;
}>();

const usagesOf = (item: Media) => props.usages[item.id] ?? [];

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
    const usages = usagesOf(item);
    const message =
        usages.length === 0
            ? `Delete "${item.filename}"?`
            : [
                  `"${item.filename}" is still used in:`,
                  ...usages.map((usage) => `• ${usage.label}`),
                  '',
                  'Those places will show no image, or a broken one if it was inserted into markdown. Delete anyway?',
              ].join('\n');

    if (!confirm(message)) {
        return;
    }

    router.delete(
        destroy(item.id, {
            query: { force: usages.length > 0 ? 1 : undefined },
        }).url,
        { preserveScroll: true },
    );
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
                accept="image/jpeg,image/png,image/gif,image/webp,image/avif,image/svg+xml"
                class="sr-only"
                :disabled="form.processing"
                @change="upload"
            />
        </label>
    </PageHeader>

    <p v-if="form.errors.file" class="cp-error mb-4">{{ form.errors.file }}</p>
    <p v-if="$page.props.errors.media" class="cp-error mb-4">
        {{ $page.props.errors.media }}
    </p>

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
                <details v-if="usagesOf(item).length" class="text-xs">
                    <summary
                        class="cursor-pointer text-neutral-600 dark:text-neutral-400"
                    >
                        Used in {{ usagesOf(item).length }}
                        {{ usagesOf(item).length === 1 ? 'place' : 'places' }}
                    </summary>
                    <ul class="mt-1 space-y-0.5 pl-3">
                        <li v-for="(usage, i) in usagesOf(item)" :key="i">
                            <Link
                                v-if="usage.post_id && usage.template_id"
                                :href="
                                    editPost([usage.template_id, usage.post_id])
                                "
                                class="hover:underline"
                            >
                                {{ usage.label }}
                            </Link>
                            <span v-else>{{ usage.label }}</span>
                        </li>
                    </ul>
                </details>
                <p v-else class="text-xs text-neutral-400">Not used anywhere</p>
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
