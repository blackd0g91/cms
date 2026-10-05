<script setup lang="ts">
import { computed, ref } from 'vue';
import { diffLines } from '@/lib/diff';
import { requestJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import { show } from '@/routes/cp/templates/posts/revisions';
import type {
    Field,
    FieldValue,
    Media,
    Post,
    PostChoices,
    PostRevision,
    PostRevisionSummary,
    Template,
} from '@/types';

const props = defineProps<{
    template: Pick<Template, 'id' | 'fields'>;
    post: Post;
    revisions: PostRevisionSummary[];
    // The current version's images, by id.
    media: Record<number, Media>;
    // For the titles of posts in posts fields.
    postChoices: PostChoices[];
}>();

const emit = defineEmits<{
    restore: [revision: PostRevision, media: Record<number, Media>];
}>();

const VISIBLE = 8;

const showAll = ref(false);
const dialog = ref<HTMLDialogElement>();
const selected = ref<PostRevision | null>(null);
const selectedMedia = ref<Record<number, Media>>({});
const loading = ref(false);
const error = ref<string | null>(null);

const visible = computed(() =>
    showAll.value ? props.revisions : props.revisions.slice(0, VISIBLE),
);

const formatTime = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

const open = async (summary: PostRevisionSummary) => {
    selected.value = null;
    error.value = null;
    loading.value = true;
    dialog.value?.showModal();

    try {
        const result = await requestJson<{
            revision: PostRevision;
            media: Record<number, Media>;
        }>(show([props.template.id, props.post.id, summary.id]).url);

        selected.value = result.revision;
        selectedMedia.value = result.media;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Could not load it';
    } finally {
        loading.value = false;
    }
};

const close = () => dialog.value?.close();

const restore = () => {
    if (selected.value) {
        emit('restore', selected.value, selectedMedia.value);
        close();
    }
};

// --- Comparing with the current saved version -----------------------------

const imageName = (id: number) =>
    (selectedMedia.value[id] ?? props.media[id])?.filename ??
    `Image #${id} (deleted)`;

const postTitles = computed(
    () =>
        new Map(
            props.postChoices.flatMap((template) =>
                template.posts.map((post) => [post.id, post.title]),
            ),
        ),
);

const asText = (field: Field | null, value: FieldValue | undefined): string => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (field?.type === 'image') {
        return imageName(value as number);
    }

    // One per line, so changes show like those to a list.
    if (field?.type === 'gallery' && Array.isArray(value)) {
        return value.map((id) => imageName(Number(id))).join('\n');
    }

    if (field?.type === 'posts' && Array.isArray(value)) {
        return value
            .map(
                (id) =>
                    postTitles.value.get(Number(id)) ?? `Post #${id} (deleted)`,
            )
            .join('\n');
    }

    if (Array.isArray(value)) {
        return value.join('\n');
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return String(value);
};

type Comparison = {
    label: string;
    before: string;
    after: string;
    changed: boolean;
    multiline: boolean;
};

const comparisons = computed<Comparison[]>(() => {
    const revision = selected.value;

    if (!revision) {
        return [];
    }

    const row = (
        label: string,
        before: string,
        after: string,
        multiline = false,
    ): Comparison => ({
        label,
        before,
        after,
        changed: before !== after,
        multiline,
    });

    const thumbnail = { type: 'image' } as Field;

    return [
        row('Title', revision.title, props.post.title),
        row('Slug', revision.slug, props.post.slug),
        row('Status', revision.status, props.post.status),
        row(
            'Thumbnail',
            asText(thumbnail, revision.thumbnail_id),
            asText(thumbnail, props.post.thumbnail_id),
        ),
        ...props.template.fields.map((field) =>
            row(
                field.label,
                asText(field, revision.data[field.handle]),
                asText(field, props.post.data[field.handle]),
                ['textarea', 'markdown', 'list', 'gallery', 'posts'].includes(
                    field.type,
                ),
            ),
        ),
    ];
});

const changedCount = computed(
    () => comparisons.value.filter((comparison) => comparison.changed).length,
);
</script>

<template>
    <section class="cp-card p-4">
        <h2 class="mb-2 text-sm font-medium">History</h2>

        <p v-if="revisions.length === 0" class="text-xs text-neutral-500">
            Versions appear here each time you save.
        </p>

        <ol v-else class="space-y-1">
            <li v-for="(revision, i) in visible" :key="revision.id">
                <button
                    type="button"
                    class="block w-full rounded-md px-2 py-1.5 text-left text-xs hover:bg-neutral-100 dark:hover:bg-neutral-800"
                    @click="open(revision)"
                >
                    <span class="block font-medium">
                        {{ formatTime(revision.created_at) }}
                        <span
                            v-if="i === 0"
                            class="ml-1 font-normal text-neutral-500"
                        >
                            (current)
                        </span>
                    </span>
                    <span class="block truncate text-neutral-500">
                        {{
                            revision.status === 'published'
                                ? 'Published'
                                : 'Draft'
                        }}
                        <template v-if="revision.user">
                            &middot; {{ revision.user }}
                        </template>
                    </span>
                </button>
            </li>
        </ol>

        <button
            v-if="revisions.length > VISIBLE"
            type="button"
            class="mt-2 text-xs text-neutral-500 underline"
            @click="showAll = !showAll"
        >
            {{ showAll ? 'Show fewer' : `Show all ${revisions.length}` }}
        </button>

        <dialog
            ref="dialog"
            class="m-auto w-full max-w-3xl rounded-lg border border-neutral-200 bg-white p-0 text-neutral-900 backdrop:bg-black/40 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100"
            @click.self="close"
        >
            <div
                class="flex items-center justify-between gap-3 border-b border-neutral-200 p-4 dark:border-neutral-800"
            >
                <div>
                    <h2 class="font-semibold">
                        Version from
                        {{ selected ? formatTime(selected.created_at) : '…' }}
                    </h2>
                    <p class="text-xs text-neutral-500">
                        <template v-if="selected && changedCount === 0">
                            Same as the current saved version.
                        </template>
                        <template v-else>
                            Compared with the current saved version:
                            <span class="text-red-600 dark:text-red-400"
                                >red is only in this version</span
                            >,
                            <span class="text-green-700 dark:text-green-400"
                                >green was added since</span
                            >.
                        </template>
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button
                        type="button"
                        class="cp-btn-primary"
                        :disabled="!selected || changedCount === 0"
                        @click="restore"
                    >
                        Restore this version
                    </button>
                    <button type="button" class="cp-btn" @click="close">
                        Close
                    </button>
                </div>
            </div>

            <div class="max-h-[65vh] space-y-4 overflow-y-auto p-4 text-sm">
                <p v-if="loading" class="text-neutral-500">Loading…</p>
                <p v-else-if="error" class="cp-error">{{ error }}</p>

                <template v-else>
                    <div
                        v-for="comparison in comparisons.filter(
                            (c) => c.changed,
                        )"
                        :key="comparison.label"
                    >
                        <p class="mb-1 text-xs font-medium text-neutral-500">
                            {{ comparison.label }}
                        </p>
                        <pre
                            v-if="comparison.multiline"
                            class="overflow-x-auto rounded-md border border-neutral-200 font-mono text-xs leading-relaxed dark:border-neutral-800"
                        ><div
                            v-for="(line, i) in diffLines(comparison.before, comparison.after)"
                            :key="i"
                            :class="cn(
                                'px-3 whitespace-pre-wrap',
                                line.type === 'removed' && 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-300',
                                line.type === 'added' && 'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-300',
                            )"
                        >{{ line.type === 'removed' ? '− ' : line.type === 'added' ? '+ ' : '  ' }}{{ line.text || ' ' }}</div></pre>
                        <p v-else class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded bg-red-50 px-1.5 text-red-800 line-through dark:bg-red-950 dark:text-red-300"
                                >{{ comparison.before || '(empty)' }}</span
                            >
                            <span aria-hidden="true">→</span>
                            <span
                                class="rounded bg-green-50 px-1.5 text-green-800 dark:bg-green-950 dark:text-green-300"
                                >{{ comparison.after || '(empty)' }}</span
                            >
                        </p>
                    </div>
                    <p
                        v-if="comparisons.some((c) => !c.changed)"
                        class="text-xs text-neutral-500"
                    >
                        Unchanged:
                        {{
                            comparisons
                                .filter((c) => !c.changed)
                                .map((c) => c.label)
                                .join(', ')
                        }}
                    </p>
                </template>
            </div>
        </dialog>
    </section>
</template>
