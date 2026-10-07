<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { timeAgo } from '@/lib/utils';
import { index as postsIndex } from '@/routes/cp/posts';
import { edit } from '@/routes/cp/templates/posts';
import type { PostListItem } from '@/types';

export type Draft = PostListItem & {
    description: string;
    reading_minutes: number;
};

// The latest draft comes first, to continue it, then the next few.
const props = defineProps<{ drafts: Draft[] }>();

const latest = computed(() => props.drafts[0]);
const others = computed(() => props.drafts.slice(1));
</script>

<template>
    <section class="cp-card">
        <div
            class="flex items-center justify-between gap-2 border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Pick up where you left off</h2>
            <Link
                :href="postsIndex({ query: { status: 'draft' } })"
                class="text-xs text-neutral-500 hover:text-neutral-900 hover:underline dark:hover:text-neutral-100"
            >
                All drafts
            </Link>
        </div>

        <p v-if="!latest" class="p-4 text-sm text-neutral-500">
            No drafts in progress. Start a new post from the button above.
        </p>

        <template v-else>
            <div
                class="flex flex-col gap-4 p-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="text-xs text-neutral-500">
                        {{ latest.template.name }} &middot; edited
                        {{ timeAgo(latest.updated_at) }}
                    </p>
                    <Link
                        :href="edit([latest.template.id, latest.id])"
                        class="mt-1 block truncate text-lg font-semibold hover:underline"
                    >
                        {{ latest.title }}
                    </Link>
                    <p
                        v-if="latest.description"
                        class="mt-1 line-clamp-2 text-sm text-neutral-600 dark:text-neutral-400"
                    >
                        {{ latest.description }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">
                        {{ latest.reading_minutes }} min read so far
                    </p>
                </div>
                <Link
                    :href="edit([latest.template.id, latest.id])"
                    class="cp-btn-primary shrink-0"
                >
                    Continue writing
                </Link>
            </div>

            <ul
                v-if="others.length"
                class="divide-y divide-neutral-200 border-t border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800"
            >
                <li
                    v-for="draft in others"
                    :key="draft.id"
                    class="flex items-baseline justify-between gap-3 px-4 py-2.5"
                >
                    <Link
                        :href="edit([draft.template.id, draft.id])"
                        class="min-w-0 truncate text-sm font-medium hover:underline"
                    >
                        {{ draft.title }}
                    </Link>
                    <span class="shrink-0 text-xs text-neutral-500">
                        {{ draft.template.name }} &middot;
                        {{ timeAgo(draft.updated_at) }}
                    </span>
                </li>
            </ul>
        </template>
    </section>
</template>
