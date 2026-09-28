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
            class="flex items-center justify-between gap-3 px-4 py-3"
        >
            <div class="min-w-0">
                <Link
                    :href="edit([post.template.id, post.id])"
                    class="block truncate text-sm font-medium hover:underline"
                >
                    {{ post.title }}
                </Link>
                <p class="text-xs text-neutral-500">
                    {{ post.template.name }} &middot;
                    <span
                        :class="
                            post.status === 'published'
                                ? 'text-green-700 dark:text-green-400'
                                : 'text-amber-700 dark:text-amber-400'
                        "
                    >
                        {{
                            post.status === 'published' ? 'Published' : 'Draft'
                        }}
                    </span>
                    &middot; {{ formatDate(post.updated_at) }}
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
