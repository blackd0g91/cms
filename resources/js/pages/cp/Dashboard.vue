<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ActivityChart from '@/components/cp/ActivityChart.vue';
import DraftsInProgress from '@/components/cp/DraftsInProgress.vue';
import type { Draft } from '@/components/cp/DraftsInProgress.vue';
import NewPostMenu from '@/components/cp/NewPostMenu.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import PopularPosts from '@/components/cp/PopularPosts.vue';
import PostList from '@/components/cp/PostList.vue';
import SiteSearches from '@/components/cp/SiteSearches.vue';
import type { SearchOverview } from '@/components/cp/SiteSearches.vue';
import SiteViews from '@/components/cp/SiteViews.vue';
import ThisWeek from '@/components/cp/ThisWeek.vue';
import type { Week } from '@/components/cp/ThisWeek.vue';
import UnsavedDrafts from '@/components/cp/UnsavedDrafts.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { health } from '@/routes/cp';
import { create as createTemplate } from '@/routes/cp/templates';
import type { DayViews, PostListItem } from '@/types';

defineOptions({ layout: CpLayout });

defineProps<{
    // Only counted for admins.
    systemWarnings: number;
    week: Week;
    drafts: Draft[];
    templates: { id: number; name: string; accent: string }[];
    recentPosts: PostListItem[];
    activity: { month: string; count: number }[];
    views: { daily: DayViews[]; total: number; previous: number };
    popular: (PostListItem & { views: number })[];
    searches: SearchOverview;
}>();
</script>

<template>
    <Head title="Dashboard" />
    <PageHeader title="Dashboard">
        <NewPostMenu :templates="templates" />
    </PageHeader>

    <Link
        v-if="systemWarnings"
        :href="health()"
        class="mb-6 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 hover:underline dark:border-red-900 dark:bg-red-950 dark:text-red-200"
    >
        <span class="font-bold" aria-hidden="true">✕</span>
        {{ systemWarnings }} system
        {{ systemWarnings === 1 ? 'issue needs' : 'issues need' }}
        attention. See Health.
    </Link>

    <p
        v-if="templates.length === 0"
        class="mb-6 rounded-lg border border-neutral-200 bg-white px-4 py-3 text-sm text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400"
    >
        <template v-if="$page.props.auth.user.role === 'admin'">
            Posts are written with a template. Create one to start writing.
            <Link :href="createTemplate()" class="ml-1 underline"
                >New template</Link
            >
        </template>
        <template v-else>
            Posts are written with a template. Ask an admin to create one.
        </template>
    </p>

    <ThisWeek :week="week" class="mb-6" />

    <DraftsInProgress :drafts="drafts" class="mb-6" />

    <UnsavedDrafts :templates="templates" class="mb-6" />

    <SiteViews
        :daily="views.daily"
        :total="views.total"
        :previous="views.previous"
        class="mb-6"
    />

    <div class="mb-6 grid gap-6 xl:grid-cols-[3fr_2fr]">
        <ActivityChart :months="activity" />
        <PopularPosts :posts="popular" />
    </div>

    <SiteSearches :searches="searches" class="mb-6" />

    <section class="cp-card">
        <h2
            class="border-b border-neutral-200 px-4 py-3 font-semibold dark:border-neutral-800"
        >
            Recently edited
        </h2>
        <PostList :posts="recentPosts" empty="No posts yet." />
    </section>
</template>
