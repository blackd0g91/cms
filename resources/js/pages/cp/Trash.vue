<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { empty } from '@/routes/cp/trash';
import {
    destroy as destroyMedia,
    restore as restoreMedia,
} from '@/routes/cp/trash/media';
import {
    destroy as destroyPost,
    restore as restorePost,
} from '@/routes/cp/trash/posts';

defineOptions({ layout: CpLayout });

type Trashed = {
    id: number;
    deleted_at: string;
    // Until it is deleted for good.
    days_left: number;
};

const props = defineProps<{
    days: number;
    posts: (Trashed & { title: string; template: string })[];
    media: (Trashed & { filename: string; url: string; thumb_url: string })[];
}>();

const isEmpty = computed(
    () => props.posts.length === 0 && props.media.length === 0,
);

const options = { preserveScroll: true };

const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });

const daysLeft = (item: Trashed) =>
    item.days_left === 1 ? '1 day left' : `${item.days_left} days left`;

const deleteForGood = (name: string, url: string) => {
    if (confirm(`Delete "${name}" for good? This can not be undone.`)) {
        router.delete(url, options);
    }
};

const emptyTrash = () => {
    const count = props.posts.length + props.media.length;

    if (
        confirm(
            `Delete ${count === 1 ? 'the 1 item' : `all ${count} items`} in the trash for good? This can not be undone.`,
        )
    ) {
        router.delete(empty().url, options);
    }
};
</script>

<template>
    <Head title="Trash" />
    <PageHeader title="Trash">
        <button
            v-if="!isEmpty"
            type="button"
            class="cp-btn-danger"
            @click="emptyTrash"
        >
            Empty trash
        </button>
    </PageHeader>

    <p class="mb-6 text-sm text-neutral-500">
        Deleted posts and images wait here for {{ days }} days, then they are
        deleted for good.
    </p>

    <p v-if="isEmpty" class="cp-card p-6 text-sm text-neutral-500">
        The trash is empty.
    </p>

    <div v-else class="max-w-3xl space-y-8">
        <section v-if="posts.length">
            <h2
                class="mb-2 text-xs font-semibold tracking-wider text-neutral-500 uppercase"
            >
                Posts
            </h2>
            <ul
                class="cp-card divide-y divide-neutral-200 dark:divide-neutral-800"
            >
                <li
                    v-for="post in posts"
                    :key="post.id"
                    class="flex flex-wrap items-center gap-3 p-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ post.title }}</p>
                        <p class="text-xs text-neutral-500">
                            {{ post.template }} &middot; deleted
                            {{ formatDate(post.deleted_at) }} &middot;
                            {{ daysLeft(post) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="cp-btn"
                        @click="
                            router.post(restorePost(post.id).url, {}, options)
                        "
                    >
                        Restore
                    </button>
                    <button
                        type="button"
                        class="cp-btn-danger"
                        @click="
                            deleteForGood(post.title, destroyPost(post.id).url)
                        "
                    >
                        Delete for good
                    </button>
                </li>
            </ul>
        </section>

        <section v-if="media.length">
            <h2
                class="mb-2 text-xs font-semibold tracking-wider text-neutral-500 uppercase"
            >
                Images
            </h2>
            <ul
                class="cp-card divide-y divide-neutral-200 dark:divide-neutral-800"
            >
                <li
                    v-for="item in media"
                    :key="item.id"
                    class="flex flex-wrap items-center gap-3 p-3"
                >
                    <a :href="item.url" target="_blank" class="shrink-0">
                        <img
                            :src="item.thumb_url"
                            :alt="item.filename"
                            class="size-12 rounded-md bg-neutral-100 object-cover dark:bg-neutral-800"
                            loading="lazy"
                        />
                    </a>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium" :title="item.filename">
                            {{ item.filename }}
                        </p>
                        <p class="text-xs text-neutral-500">
                            Deleted {{ formatDate(item.deleted_at) }} &middot;
                            {{ daysLeft(item) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="cp-btn"
                        @click="
                            router.post(restoreMedia(item.id).url, {}, options)
                        "
                    >
                        Restore
                    </button>
                    <button
                        type="button"
                        class="cp-btn-danger"
                        @click="
                            deleteForGood(
                                item.filename,
                                destroyMedia(item.id).url,
                            )
                        "
                    >
                        Delete for good
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
