<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { edit } from '@/routes/cp/templates/posts';
import type { PostListItem } from '@/types';

defineProps<{
    posts: PostListItem[];
    empty: string;
}>();

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <p v-if="posts.length === 0" class="p-4 text-sm text-neutral-500">
        {{ empty }}
    </p>
    <ul v-else class="divide-y divide-neutral-200 dark:divide-neutral-800">
        <li
            v-for="post in posts"
            :key="post.id"
            class="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-800/40"
        >
            <div class="min-w-0">
                <Link
                    :href="edit([post.template.id, post.id])"
                    class="block truncate text-sm font-medium hover:underline"
                >
                    {{ post.title }}
                </Link>
                <p
                    class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-neutral-500"
                >
                    <span
                        :class="[
                            'cp-badge',
                            post.status === 'published'
                                ? 'cp-badge-published'
                                : 'cp-badge-draft',
                        ]"
                    >
                        {{
                            post.status === 'published' ? 'Published' : 'Draft'
                        }}
                    </span>
                    <span
                        v-if="post.pinned"
                        class="cp-badge bg-neutral-50 text-neutral-600 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700"
                        title="Pinned to the top"
                        >📌 Pinned</span
                    >
                    <span>{{ post.template.name }}</span>
                    <template v-if="post.author">
                        <span aria-hidden="true">&middot;</span>
                        <span>{{ post.author }}</span>
                    </template>
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ formatDate(post.updated_at) }}</span>
                </p>
            </div>
            <a
                :href="post.url"
                target="_blank"
                class="shrink-0 text-xs text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
            >
                View
            </a>
        </li>
    </ul>
</template>
