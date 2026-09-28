<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { edit as editTemplate } from '@/routes/cp/templates';
import { create, edit } from '@/routes/cp/templates/posts';
import type { Post, TemplateSummary } from '@/types';

defineOptions({ layout: CpLayout });

defineProps<{
    template: TemplateSummary;
    posts: Omit<Post, 'data'>[];
}>();

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <Head :title="template.name" />
    <PageHeader :title="template.name">
        <Link :href="editTemplate(template.id)" class="cp-btn">
            Edit template
        </Link>
        <Link :href="create(template.id)" class="cp-btn-primary">New post</Link>
    </PageHeader>

    <p v-if="posts.length === 0" class="cp-card p-6 text-sm text-neutral-500">
        No posts yet.
    </p>

    <ul
        v-else
        class="cp-card divide-y divide-neutral-200 dark:divide-neutral-800"
    >
        <li
            v-for="post in posts"
            :key="post.id"
            class="flex flex-wrap items-center justify-between gap-3 p-4"
        >
            <div class="min-w-0">
                <Link
                    :href="edit([template.id, post.id])"
                    class="font-medium hover:underline"
                >
                    {{ post.title }}
                </Link>
                <p class="text-sm text-neutral-500">
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
                    &middot; updated {{ formatDate(post.updated_at) }}
                </p>
            </div>
            <div class="flex gap-2">
                <a :href="post.url" target="_blank" class="cp-btn">View</a>
                <Link :href="edit([template.id, post.id])" class="cp-btn">
                    Edit
                </Link>
            </div>
        </li>
    </ul>
</template>
