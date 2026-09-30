<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmojiPicker from '@/components/cp/EmojiPicker.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import CpLayout from '@/layouts/CpLayout.vue';
import { update } from '@/routes/cp/links';
import type { PostStatus } from '@/types';

defineOptions({ layout: CpLayout });

type SavedLink = {
    id: number;
    label: string | null;
    url: string | null;
    post_id: number | null;
    emoji: string | null;
};

const props = defineProps<{
    heading: string;
    links: SavedLink[];
    templates: {
        id: number;
        name: string;
        posts: { id: number; title: string; status: PostStatus }[];
    }[];
}>();

type EditableLink = {
    // Stable across moves, for the list; id is null until saved.
    key: number;
    id: number | null;
    to: 'url' | 'post';
    label: string;
    url: string;
    post_id: number | null;
    emoji: string;
};

let nextKey = 0;

const editable = (links: SavedLink[]): EditableLink[] =>
    links.map((link) => ({
        key: nextKey++,
        id: link.id,
        to: link.post_id === null ? 'url' : 'post',
        label: link.label ?? '',
        url: link.url ?? '',
        post_id: link.post_id,
        emoji: link.emoji ?? '',
    }));

const form = useForm({
    heading: props.heading,
    links: editable(props.links),
});

const posts = computed(
    () =>
        new Map(
            props.templates.flatMap((template) =>
                template.posts.map((post) => [post.id, post]),
            ),
        ),
);

const addLink = () => {
    form.links.push({
        key: nextKey++,
        id: null,
        to: 'url',
        label: '',
        url: '',
        post_id: null,
        emoji: '',
    });
};

const moveLink = (index: number, offset: number) => {
    const [link] = form.links.splice(index, 1);
    form.links.splice(index + offset, 0, link);
};

const removeLink = (index: number) => {
    form.links.splice(index, 1);
};

const linkError = (index: number, key: string) =>
    form.errors[`links.${index}.${key}` as keyof typeof form.errors];

const submit = () => {
    form.transform((data) => ({
        heading: data.heading,
        links: data.links.map((link) => ({
            id: link.id,
            label: link.label,
            url: link.to === 'url' ? link.url : null,
            post_id: link.to === 'post' ? link.post_id : null,
            emoji: link.emoji,
        })),
    })).put(update().url, {
        preserveScroll: true,
        onSuccess: () => {
            // New links now have ids, so saving again updates them.
            form.links = editable(props.links);
            form.heading = props.heading;
            form.defaults();
        },
    });
};

useUnsavedChanges({
    isDirty: () => form.isDirty,
    save: () => !form.processing && submit(),
});
</script>

<template>
    <Head title="Links" />
    <PageHeader title="Links">
        <a href="/" target="_blank" class="cp-btn">View site</a>
    </PageHeader>

    <p class="mb-6 text-sm text-neutral-500">
        Shown at the bottom of the site's sidebar, and in the menu on phones.
    </p>

    <form class="max-w-3xl space-y-6" @submit.prevent="submit">
        <section class="cp-card space-y-1.5 p-6">
            <label for="heading" class="cp-label">Heading</label>
            <input
                id="heading"
                v-model="form.heading"
                type="text"
                placeholder="No heading"
                class="cp-input"
            />
            <p class="text-xs text-neutral-500">
                Shown above the links, like "Links" or "Elsewhere". Leave it
                empty for none.
            </p>
            <p v-if="form.errors.heading" class="cp-error">
                {{ form.errors.heading }}
            </p>
        </section>

        <section class="cp-card p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold">Links</h2>
                <button type="button" class="cp-btn" @click="addLink">
                    Add link
                </button>
            </div>

            <p v-if="form.links.length === 0" class="text-sm text-neutral-500">
                No links yet.
            </p>

            <ol class="space-y-3">
                <li
                    v-for="(link, i) in form.links"
                    :key="link.key"
                    class="rounded-md border border-neutral-200 p-4 dark:border-neutral-800"
                >
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="flex items-center gap-1">
                            <EmojiPicker
                                :button-label="
                                    link.emoji
                                        ? `Change the emoji, now ${link.emoji}`
                                        : 'Choose an emoji'
                                "
                                button-class="cp-btn size-10 p-0 text-lg"
                                @select="link.emoji = $event"
                            >
                                <span v-if="link.emoji">{{ link.emoji }}</span>
                                <span v-else class="text-neutral-400">+</span>
                            </EmojiPicker>
                            <!-- Kept in place when hidden, so every row lines up. -->
                            <button
                                type="button"
                                :class="[
                                    'text-xs text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200',
                                    !link.emoji && 'invisible',
                                ]"
                                :disabled="!link.emoji"
                                aria-label="Remove the emoji"
                                @click="link.emoji = ''"
                            >
                                &times;
                            </button>
                        </div>

                        <fieldset class="min-w-0 flex-1 space-y-3">
                            <legend class="sr-only">Link {{ i + 1 }}</legend>

                            <div class="flex gap-4 text-sm">
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="link.to"
                                        type="radio"
                                        value="url"
                                    />
                                    An address
                                </label>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="link.to"
                                        type="radio"
                                        value="post"
                                    />
                                    One of my posts
                                </label>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-if="link.to === 'url'"
                                    class="space-y-1.5"
                                >
                                    <label
                                        :for="`link-${link.key}-url`"
                                        class="cp-label"
                                    >
                                        Address
                                    </label>
                                    <input
                                        :id="`link-${link.key}-url`"
                                        v-model="link.url"
                                        type="text"
                                        inputmode="url"
                                        placeholder="github.com/you"
                                        class="cp-input"
                                    />
                                    <p
                                        v-if="linkError(i, 'url')"
                                        class="cp-error"
                                    >
                                        {{ linkError(i, 'url') }}
                                    </p>
                                </div>

                                <div v-else class="space-y-1.5">
                                    <label
                                        :for="`link-${link.key}-post`"
                                        class="cp-label"
                                    >
                                        Post
                                    </label>
                                    <select
                                        :id="`link-${link.key}-post`"
                                        v-model="link.post_id"
                                        class="cp-input"
                                    >
                                        <option :value="null" disabled>
                                            Pick a post
                                        </option>
                                        <optgroup
                                            v-for="template in templates"
                                            :key="template.id"
                                            :label="template.name"
                                        >
                                            <option
                                                v-for="post in template.posts"
                                                :key="post.id"
                                                :value="post.id"
                                            >
                                                {{ post.title
                                                }}{{
                                                    post.status === 'draft'
                                                        ? ' (draft)'
                                                        : ''
                                                }}
                                            </option>
                                        </optgroup>
                                    </select>
                                    <p
                                        v-if="linkError(i, 'post_id')"
                                        class="cp-error"
                                    >
                                        {{ linkError(i, 'post_id') }}
                                    </p>
                                    <p
                                        v-else-if="
                                            link.post_id !== null &&
                                            posts.get(link.post_id)?.status ===
                                                'draft'
                                        "
                                        class="text-xs text-amber-700 dark:text-amber-400"
                                    >
                                        A draft: the link shows once it's
                                        published.
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <label
                                        :for="`link-${link.key}-label`"
                                        class="cp-label"
                                    >
                                        Label
                                    </label>
                                    <input
                                        :id="`link-${link.key}-label`"
                                        v-model="link.label"
                                        type="text"
                                        :placeholder="
                                            link.to === 'post'
                                                ? (posts.get(link.post_id ?? -1)
                                                      ?.title ??
                                                  'The post title')
                                                : ''
                                        "
                                        class="cp-input"
                                    />
                                    <p
                                        v-if="linkError(i, 'label')"
                                        class="cp-error"
                                    >
                                        {{ linkError(i, 'label') }}
                                    </p>
                                    <p
                                        v-else-if="link.to === 'post'"
                                        class="text-xs text-neutral-500"
                                    >
                                        Optional: without one, the link shows
                                        the post's title.
                                    </p>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <p v-if="linkError(i, 'emoji')" class="cp-error mt-2">
                        {{ linkError(i, 'emoji') }}
                    </p>

                    <div class="mt-3 flex justify-end gap-1">
                        <button
                            type="button"
                            class="cp-btn"
                            :disabled="i === 0"
                            aria-label="Move up"
                            @click="moveLink(i, -1)"
                        >
                            &uarr;
                        </button>
                        <button
                            type="button"
                            class="cp-btn"
                            :disabled="i === form.links.length - 1"
                            aria-label="Move down"
                            @click="moveLink(i, 1)"
                        >
                            &darr;
                        </button>
                        <button
                            type="button"
                            class="cp-btn-danger"
                            @click="removeLink(i)"
                        >
                            Remove
                        </button>
                    </div>
                </li>
            </ol>
        </section>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                class="cp-btn-primary"
                :disabled="form.processing"
            >
                Save
            </button>
            <span
                v-if="form.recentlySuccessful"
                class="text-sm text-neutral-500"
            >
                Saved.
            </span>
            <span
                v-else-if="form.isDirty"
                class="text-sm text-amber-700 dark:text-amber-400"
            >
                Unsaved changes &middot; Ctrl+S to save
            </span>
        </div>
    </form>
</template>
