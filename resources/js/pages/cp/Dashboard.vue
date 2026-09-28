<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import PostList from '@/components/cp/PostList.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { index as mediaIndex } from '@/routes/cp/media';
import { create as createTemplate } from '@/routes/cp/templates';
import { index as postsIndex } from '@/routes/cp/posts';
import { create as createPost } from '@/routes/cp/templates/posts';
import type { PostListItem, TemplateSummary } from '@/types';

defineOptions({ layout: CpLayout });

defineProps<{
    stats: {
        published: number;
        drafts: number;
        templates: number;
        media: number;
    };
    templates: (TemplateSummary & {
        posts_count: number;
        drafts_count: number;
    })[];
    recentPosts: PostListItem[];
    drafts: PostListItem[];
}>();
</script>

<template>
    <Head title="Dashboard" />
    <PageHeader title="Dashboard">
        <a href="/" target="_blank" class="cp-btn">View site</a>
    </PageHeader>

    <p class="-mt-4 mb-6 text-sm text-neutral-600 dark:text-neutral-400">
        Welcome back, {{ $page.props.auth.user.name }}.
    </p>

    <dl class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="cp-card p-4">
            <dt class="text-sm text-neutral-500">Published</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ stats.published }}</dd>
        </div>
        <div class="cp-card p-4">
            <dt class="text-sm text-neutral-500">Drafts</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ stats.drafts }}</dd>
        </div>
        <div class="cp-card p-4">
            <dt class="text-sm text-neutral-500">Templates</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ stats.templates }}</dd>
        </div>
        <Link
            :href="mediaIndex()"
            class="cp-card block p-4 hover:border-neutral-400"
        >
            <dt class="text-sm text-neutral-500">Images</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ stats.media }}</dd>
        </Link>
    </dl>

    <section class="cp-card mb-6">
        <h2
            class="border-b border-neutral-200 px-4 py-3 font-semibold dark:border-neutral-800"
        >
            New post
        </h2>
        <div v-if="templates.length === 0" class="p-4 text-sm text-neutral-500">
            Create a template first to start writing posts.
            <Link :href="createTemplate()" class="ml-1 underline"
                >New template</Link
            >
        </div>
        <ul v-else class="divide-y divide-neutral-200 dark:divide-neutral-800">
            <li
                v-for="template in templates"
                :key="template.id"
                class="flex items-center justify-between gap-3 px-4 py-3"
            >
                <div class="min-w-0">
                    <Link
                        :href="postsIndex({ query: { template: template.id } })"
                        class="text-sm font-medium hover:underline"
                    >
                        {{ template.name }}
                    </Link>
                    <p class="text-xs text-neutral-500">
                        {{ template.posts_count }}
                        {{ template.posts_count === 1 ? 'post' : 'posts' }}
                        <template v-if="template.drafts_count">
                            &middot; {{ template.drafts_count }}
                            {{
                                template.drafts_count === 1 ? 'draft' : 'drafts'
                            }}
                        </template>
                    </p>
                </div>
                <Link :href="createPost(template.id)" class="cp-btn shrink-0">
                    New {{ template.name }} post
                </Link>
            </li>
        </ul>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="cp-card">
            <h2
                class="border-b border-neutral-200 px-4 py-3 font-semibold dark:border-neutral-800"
            >
                Recently edited
            </h2>
            <PostList :posts="recentPosts" empty="No posts yet." />
        </section>
        <section class="cp-card">
            <h2
                class="border-b border-neutral-200 px-4 py-3 font-semibold dark:border-neutral-800"
            >
                Drafts
            </h2>
            <PostList
                :posts="drafts"
                empty="No drafts. Everything is published."
            />
        </section>
    </div>
</template>
