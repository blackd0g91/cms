<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

const usageFilter = ref<'all' | 'used' | 'unused'>('all');
const typeFilter = ref('all');
const dateFilter = ref<'all' | '7' | '30' | '365'>('all');
const search = ref('');

const typeLabel = (mime: string) =>
    mime === 'image/svg+xml'
        ? 'SVG'
        : mime.replace('image/', '').replace('jpeg', 'jpg').toUpperCase();

const types = computed(() =>
    [...new Set(props.media.map((item) => item.mime_type))].sort(),
);

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();
    const since =
        dateFilter.value === 'all'
            ? null
            : Date.now() - Number(dateFilter.value) * 86_400_000;

    return props.media.filter((item) => {
        const used = usagesOf(item).length > 0;

        return (
            (usageFilter.value === 'all' ||
                used === (usageFilter.value === 'used')) &&
            (typeFilter.value === 'all' ||
                item.mime_type === typeFilter.value) &&
            (since === null || new Date(item.created_at).getTime() >= since) &&
            (term === '' || item.filename.toLowerCase().includes(term))
        );
    });
});

const filtering = computed(
    () =>
        usageFilter.value !== 'all' ||
        typeFilter.value !== 'all' ||
        dateFilter.value !== 'all' ||
        search.value.trim() !== '',
);

const clearFilters = () => {
    usageFilter.value = 'all';
    typeFilter.value = 'all';
    dateFilter.value = 'all';
    search.value = '';
};

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
            ? `Move "${item.filename}" to the trash? It can be restored from there for a while.`
            : [
                  `"${item.filename}" is still used in:`,
                  ...usages.map((usage) => `• ${usage.label}`),
                  '',
                  'Move it to the trash anyway? Thumbnails and image fields stop showing it right away, and markdown shows a broken image once it is deleted for good.',
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

    <form
        v-if="media.length > 0"
        class="mb-4 flex flex-wrap items-center gap-2"
        role="search"
        @submit.prevent
    >
        <input
            v-model="search"
            type="search"
            placeholder="Search filenames"
            aria-label="Search filenames"
            class="cp-input w-48"
        />
        <select
            v-model="usageFilter"
            aria-label="Filter by usage"
            class="cp-input w-auto"
        >
            <option value="all">Used and unused</option>
            <option value="used">Used</option>
            <option value="unused">Unused</option>
        </select>
        <select
            v-model="typeFilter"
            aria-label="Filter by file type"
            class="cp-input w-auto"
        >
            <option value="all">All types</option>
            <option v-for="type in types" :key="type" :value="type">
                {{ typeLabel(type) }}
            </option>
        </select>
        <select
            v-model="dateFilter"
            aria-label="Filter by upload date"
            class="cp-input w-auto"
        >
            <option value="all">Any time</option>
            <option value="7">Last 7 days</option>
            <option value="30">Last 30 days</option>
            <option value="365">Last year</option>
        </select>
        <button
            v-if="filtering"
            type="button"
            class="cp-btn text-xs"
            @click="clearFilters"
        >
            Clear
        </button>
        <span class="text-xs text-neutral-500" aria-live="polite">
            {{ visible.length }} of {{ media.length }}
        </span>
    </form>

    <p
        v-if="media.length > 0 && visible.length === 0"
        class="cp-card p-6 text-sm text-neutral-500"
    >
        No images match these filters.
    </p>

    <ul
        v-else-if="media.length > 0"
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
    >
        <li
            v-for="item in visible"
            :key="item.id"
            class="cp-card overflow-hidden"
        >
            <a :href="item.url" target="_blank">
                <img
                    :src="item.thumb_url ?? item.url"
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
