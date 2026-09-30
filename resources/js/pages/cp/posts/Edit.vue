<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FieldInput from '@/components/cp/FieldInput.vue';
import ImageField from '@/components/cp/ImageField.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import PostHistory from '@/components/cp/PostHistory.vue';
import PostViews from '@/components/cp/PostViews.vue';
import SaveButton from '@/components/cp/SaveButton.vue';
import type { ViewHistory } from '@/components/cp/PostViews.vue';
import TagInput from '@/components/cp/TagInput.vue';
import { useLocalDraft } from '@/composables/useLocalDraft';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import CpLayout from '@/layouts/CpLayout.vue';
import { requestJson } from '@/lib/http';
import { slugify } from '@/lib/utils';
import { library } from '@/routes/cp/media';
import { index } from '@/routes/cp/posts';
import { destroy, duplicate, store, update } from '@/routes/cp/templates/posts';
import type {
    Field,
    FieldValue,
    Media,
    Post,
    PostRevision,
    PostRevisionSummary,
    PostStatus,
    Template,
} from '@/types';

defineOptions({ layout: CpLayout });

const props = defineProps<{
    template: Pick<Template, 'id' | 'name' | 'handle' | 'fields'>;
    post: Post | null;
    media: Record<number, Media>;
    revisions: PostRevisionSummary[];
    // Null for a new post.
    views: ViewHistory | null;
    allTags: string[];
}>();

const emptyValue = (field: Field): FieldValue => {
    switch (field.type) {
        case 'boolean':
            return false;
        case 'list':
            return [''];
        default:
            return null;
    }
};

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    status: (props.post?.status ?? 'draft') as PostStatus,
    thumbnail_id: props.post?.thumbnail_id ?? null,
    tags: props.post?.tags ?? ([] as string[]),
    pinned: props.post?.pinned ?? false,
    // "data" is reserved by useForm, so it is renamed when submitting.
    values: Object.fromEntries(
        props.template.fields.map((field) => [
            field.handle,
            props.post?.data[field.handle] ?? emptyValue(field),
        ]),
    ) as Record<string, FieldValue>,
});

const slugTouched = ref(props.post !== null);

const onTitleInput = () => {
    if (!slugTouched.value) {
        form.slug = slugify(form.title);
    }
};

const dataError = (handle: string) => {
    const key = `data.${handle}`;

    return Object.entries(form.errors).find(
        ([name]) => name === key || name.startsWith(`${key}.`),
    )?.[1];
};

// --- Autosave -------------------------------------------------------------

type DraftData = {
    title: string;
    slug: string;
    status: PostStatus;
    thumbnail_id: number | null;
    // Missing in versions from post history, which do not track these.
    tags?: string[];
    pinned?: boolean;
    values: Record<string, FieldValue>;
};

// Pass null for the key a post had before it was first saved.
const draftKey = (postId: number | null | undefined = props.post?.id) =>
    `post.${props.template.id}.${postId ?? 'new'}`;

const draft = useLocalDraft<DraftData>({
    key: () => draftKey(),
    data: () => ({
        title: form.title,
        slug: form.slug,
        status: form.status,
        thumbnail_id: form.thumbnail_id,
        tags: form.tags,
        pinned: form.pinned,
        values: form.values,
    }),
    isDirty: () => form.isDirty,
    version: () => props.post?.updated_at ?? null,
});

const draftIsOutdated = computed(
    () =>
        draft.found.value !== null &&
        draft.found.value.version !== (props.post?.updated_at ?? null),
);

const timeAgo = (iso: string) => {
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 48) {
        return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }

    return new Date(iso).toLocaleString();
};

// Images picked while editing are not in the page's media list, so after a
// restore their previews are looked up in the library.
const knownMedia = ref<Record<number, Media>>({ ...props.media });
// Changing this remounts the fields, so they show the restored values.
const restoreCount = ref(0);

/**
 * Put saved data (an autosaved draft or an older version) into the form. It
 * stays unsaved until the Save button is pressed.
 */
const applyData = async (
    data: DraftData,
    media: Record<number, Media> = {},
) => {
    form.title = data.title;
    form.slug = data.slug;
    form.status = data.status;
    form.thumbnail_id = data.thumbnail_id;
    form.tags = data.tags ?? form.tags;
    form.pinned = data.pinned ?? form.pinned;
    form.values = { ...form.values, ...data.values };
    slugTouched.value = true;
    Object.assign(knownMedia.value, media);

    const imageIds = [
        data.thumbnail_id,
        ...props.template.fields
            .filter((field) => field.type === 'image')
            .map((field) => data.values[field.handle]),
    ].filter((id): id is number => typeof id === 'number');

    if (imageIds.some((id) => !knownMedia.value[id])) {
        try {
            const result = await requestJson<{ media: Media[] }>(library().url);
            result.media.forEach((item) => (knownMedia.value[item.id] = item));
        } catch {
            // The ids are restored either way; only the previews are missing.
        }
    }

    restoreCount.value++;
};

const restoreDraft = async () => {
    if (draft.found.value) {
        await applyData(draft.found.value.data);
        draft.dismiss();
    }
};

const restoreRevision = (
    revision: PostRevision,
    media: Record<number, Media>,
) =>
    applyData(
        {
            title: revision.title,
            slug: revision.slug,
            status: revision.status,
            thumbnail_id: revision.thumbnail_id,
            values: revision.data,
        },
        media,
    );

const submit = () => {
    form.transform(({ values, ...rest }) => ({ ...rest, data: values })).submit(
        props.post
            ? update([props.template.id, props.post.id])
            : store(props.template.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                // The page stays mounted after creating, so the slug is now fixed.
                slugTouched.value = true;
                form.defaults();
                // Saved, so the browser copy is no longer needed. A new post
                // also forgets the draft it had before it got an id.
                draft.clear();
                draft.clear(draftKey(null));
                draft.dismiss();
            },
        },
    );
};

useUnsavedChanges({
    isDirty: () => form.isDirty,
    save: () => !form.processing && submit(),
});

const deletePost = () => {
    if (!props.post || !confirm(`Delete "${props.post.title}"?`)) {
        return;
    }

    router.delete(destroy([props.template.id, props.post.id]).url);
};
</script>

<template>
    <Head :title="post ? post.title : `New ${template.name} post`" />
    <PageHeader
        :title="post ? post.title : 'New post'"
        :crumbs="[
            { label: 'Posts', href: index().url },
            {
                label: template.name,
                href: index({ query: { template: template.id } }).url,
            },
        ]"
    >
        <template v-if="post">
            <button
                type="button"
                class="cp-btn-danger"
                title="Delete this post"
                @click="deletePost"
            >
                Delete
            </button>
            <Link
                :href="duplicate([template.id, post.id])"
                method="post"
                as="button"
                :preserve-state="false"
                class="cp-btn"
                :disabled="form.isDirty"
                :title="
                    form.isDirty
                        ? 'Save your changes before duplicating'
                        : 'Create a draft copy of this post'
                "
            >
                Duplicate
            </Link>
            <a :href="post.url" target="_blank" class="cp-btn">View</a>
        </template>
        <SaveButton
            form="post-form"
            :label="post ? 'Save' : 'Create post'"
            :processing="form.processing"
            :dirty="form.isDirty"
            :saved="form.recentlySuccessful"
        />
    </PageHeader>

    <div
        v-if="draft.found.value"
        class="mb-6 flex max-w-5xl flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
        role="status"
    >
        <div>
            <p class="font-medium">
                Unsaved changes from {{ timeAgo(draft.found.value.savedAt) }}
                were found in this browser.
            </p>
            <p v-if="draftIsOutdated" class="mt-0.5 text-xs">
                This post has been saved again since then. Restoring and saving
                will replace those newer changes.
            </p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="cp-btn" @click="restoreDraft">
                Restore
            </button>
            <button type="button" class="cp-btn-danger" @click="draft.discard">
                Discard
            </button>
        </div>
    </div>

    <form
        id="post-form"
        class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_16rem]"
        @submit.prevent="submit"
    >
        <section class="cp-card space-y-5 p-6">
            <div class="space-y-1.5">
                <label for="title" class="cp-label">Title</label>
                <input
                    id="title"
                    v-model="form.title"
                    v-focus="!post"
                    type="text"
                    class="cp-input text-base"
                    @input="onTitleInput"
                />
                <p v-if="form.errors.title" class="cp-error">
                    {{ form.errors.title }}
                </p>
            </div>

            <div
                v-for="field in template.fields"
                :key="`${field.handle}-${restoreCount}`"
                class="space-y-1.5"
            >
                <label :for="`data-${field.handle}`" class="cp-label">
                    {{ field.label }}
                    <span
                        v-if="field.required && field.type !== 'boolean'"
                        class="text-red-500"
                        >*</span
                    >
                </label>
                <FieldInput
                    :id="`data-${field.handle}`"
                    v-model="form.values[field.handle]"
                    :field="field"
                    :media="
                        field.type === 'image'
                            ? knownMedia[form.values[field.handle] as number]
                            : null
                    "
                />
                <p v-if="dataError(field.handle)" class="cp-error">
                    {{ dataError(field.handle) }}
                </p>
            </div>
        </section>

        <aside class="space-y-4">
            <section class="cp-card space-y-2 p-4">
                <label for="thumbnail" class="cp-label">Thumbnail</label>
                <ImageField
                    id="thumbnail"
                    :key="`thumbnail-${restoreCount}`"
                    v-model="form.thumbnail_id"
                    :initial="
                        form.thumbnail_id ? knownMedia[form.thumbnail_id] : null
                    "
                />
                <p v-if="form.errors.thumbnail_id" class="cp-error">
                    {{ form.errors.thumbnail_id }}
                </p>
            </section>

            <section class="cp-card space-y-2 p-4">
                <label for="tags" class="cp-label">Tags</label>
                <TagInput
                    id="tags"
                    :key="`tags-${restoreCount}`"
                    v-model="form.tags"
                    :suggestions="allTags"
                />
                <p class="text-xs text-neutral-500">Enter or comma to add.</p>
                <p v-if="form.errors.tags" class="cp-error">
                    {{ form.errors.tags }}
                </p>
            </section>

            <section class="cp-card space-y-4 p-4">
                <div class="space-y-1.5">
                    <label for="status" class="cp-label">Status</label>
                    <select id="status" v-model="form.status" class="cp-input">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.pinned"
                        type="checkbox"
                        class="rounded border-neutral-300 dark:border-neutral-700"
                    />
                    Pin to the top of listings
                </label>

                <div class="space-y-1.5">
                    <label for="slug" class="cp-label">Slug</label>
                    <input
                        id="slug"
                        v-model="form.slug"
                        type="text"
                        class="cp-input font-mono"
                        @input="slugTouched = true"
                    />
                    <p class="text-xs break-all text-neutral-500">
                        /{{ template.handle }}/{{ form.slug }}
                    </p>
                    <p v-if="form.errors.slug" class="cp-error">
                        {{ form.errors.slug }}
                    </p>
                </div>
            </section>

            <!-- Once published, or when it was and has views from then. -->
            <PostViews
                v-if="
                    post &&
                    views &&
                    (post.status === 'published' || views.total > 0)
                "
                :history="views"
            />

            <PostHistory
                v-if="post"
                :template="template"
                :post="post"
                :revisions="revisions"
                @restore="restoreRevision"
            />
        </aside>
    </form>
</template>
