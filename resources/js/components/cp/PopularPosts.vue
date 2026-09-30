<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { edit } from '@/routes/cp/templates/posts';
import type { PostListItem } from '@/types';

const props = defineProps<{
    posts: (PostListItem & { views: number })[];
}>();

const largest = computed(() =>
    Math.max(1, ...props.posts.map((post) => post.views)),
);

const views = (count: number) =>
    `${count.toLocaleString()} ${count === 1 ? 'view' : 'views'}`;
</script>

<template>
    <section class="cp-card">
        <div
            class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Popular posts</h2>
            <p class="text-xs text-neutral-500">
                Most viewed in the last 30 days
            </p>
        </div>

        <p v-if="posts.length === 0" class="p-4 text-sm text-neutral-500">
            No views in the last 30 days.
        </p>

        <ol v-else class="space-y-3 p-4">
            <li
                v-for="(post, i) in posts"
                :key="post.id"
                class="grid grid-cols-[1.25rem_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1"
            >
                <span class="text-xs text-neutral-400 tabular-nums">{{
                    i + 1
                }}</span>
                <div class="min-w-0">
                    <Link
                        :href="edit([post.template.id, post.id])"
                        class="block truncate text-sm font-medium hover:underline"
                    >
                        {{ post.title }}
                    </Link>
                    <p class="text-xs text-neutral-500">
                        {{ post.template.name }}
                    </p>
                </div>
                <span
                    class="text-sm text-neutral-700 tabular-nums dark:text-neutral-300"
                >
                    {{ views(post.views) }}
                </span>
                <!-- Share of the most viewed post, on one scale. -->
                <span
                    class="col-start-2 col-end-4 h-1.5 rounded-[4px] bg-[#2a78d6] dark:bg-[#3987e5]"
                    :style="{ width: `${(post.views / largest) * 100}%` }"
                    aria-hidden="true"
                />
            </li>
        </ol>
    </section>
</template>
