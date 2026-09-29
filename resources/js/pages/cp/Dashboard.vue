<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ContentCheckup from '@/components/cp/ContentCheckup.vue';
import type { Check } from '@/components/cp/ContentCheckup.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import PostList from '@/components/cp/PostList.vue';
import UnsavedDrafts from '@/components/cp/UnsavedDrafts.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { index as mediaIndex } from '@/routes/cp/media';
import { create as createTemplate } from '@/routes/cp/templates';
import { index as postsIndex } from '@/routes/cp/posts';
import { create as createPost } from '@/routes/cp/templates/posts';
import type { PostListItem, TemplateSummary } from '@/types';

defineOptions({ layout: CpLayout });

const props = defineProps<{
    stats: {
        published: number;
        drafts: number;
        templates: number;
        media: number;
    };
    templates: (TemplateSummary & {
        posts_count: number;
        drafts_count: number;
        accent: string;
    })[];
    recentPosts: PostListItem[];
    drafts: PostListItem[];
    checkup: Check[];
}>();

type TemplateRow = (typeof props.templates)[number];

const published = (template: TemplateRow) =>
    template.posts_count - template.drafts_count;

// Every bar shares one scale: the template with the most posts is full width.
const largest = computed(() =>
    Math.max(1, ...props.templates.map((template) => template.posts_count)),
);

const share = (count: number) => `${(count / largest.value) * 100}%`;
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

    <UnsavedDrafts :templates="templates" class="mb-6" />

    <ContentCheckup :checks="checkup" class="mb-6" />

    <section class="cp-card mb-6">
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Templates</h2>
            <p
                v-if="templates.length"
                class="flex items-center gap-3 text-xs text-neutral-500"
                aria-hidden="true"
            >
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-3 rounded-sm bg-neutral-500" />
                    Published
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-3 rounded-sm bg-neutral-500/35" />
                    Drafts
                </span>
            </p>
        </div>
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
                class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-4 px-4 py-3"
            >
                <div class="min-w-0">
                    <Link
                        :href="postsIndex({ query: { template: template.id } })"
                        class="flex items-center gap-2 text-sm font-medium hover:underline"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: template.accent }"
                            aria-hidden="true"
                        />
                        <span class="truncate">{{ template.name }}</span>
                    </Link>
                    <p class="text-xs text-neutral-500">
                        {{ published(template) }} published
                        <template v-if="template.drafts_count">
                            &middot; {{ template.drafts_count }}
                            {{
                                template.drafts_count === 1 ? 'draft' : 'drafts'
                            }}
                        </template>
                    </p>
                </div>

                <!-- Published and drafts, on a scale shared by every template. -->
                <div
                    class="flex h-2.5 items-center gap-[2px]"
                    role="img"
                    :aria-label="`${template.name}: ${published(template)} published, ${template.drafts_count} ${template.drafts_count === 1 ? 'draft' : 'drafts'}`"
                >
                    <span
                        v-if="published(template)"
                        class="h-full rounded-[4px]"
                        :style="{
                            width: share(published(template)),
                            backgroundColor: template.accent,
                        }"
                        :title="`${published(template)} published`"
                    />
                    <span
                        v-if="template.drafts_count"
                        class="h-full rounded-[4px]"
                        :style="{
                            width: share(template.drafts_count),
                            backgroundColor: `color-mix(in oklch, ${template.accent} 35%, transparent)`,
                        }"
                        :title="`${template.drafts_count} ${template.drafts_count === 1 ? 'draft' : 'drafts'}`"
                    />
                </div>

                <Link :href="createPost(template.id)" class="cp-btn shrink-0">
                    New post
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
