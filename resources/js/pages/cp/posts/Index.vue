<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import PostList from '@/components/cp/PostList.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { create, index } from '@/routes/cp/posts';
import type { Paginated, PostListItem, PostStatus } from '@/types';

defineOptions({ layout: CpLayout });

const props = defineProps<{
    posts: Paginated<PostListItem>;
    templates: { id: number; name: string }[];
    filters: { template: number | null; status: PostStatus | null };
}>();

const filter = (changes: Partial<typeof props.filters>) => {
    const query = { ...props.filters, ...changes };

    router.get(
        index({
            query: {
                template: query.template ?? undefined,
                status: query.status ?? undefined,
            },
        }).url,
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const selectValue = (event: Event) =>
    (event.target as HTMLSelectElement).value || null;
</script>

<template>
    <Head title="Posts" />
    <PageHeader title="Posts">
        <Link :href="create()" class="cp-btn-primary">New post</Link>
    </PageHeader>

    <div class="mb-4 flex flex-wrap gap-3">
        <select
            :value="filters.template ?? ''"
            aria-label="Filter by template"
            class="cp-input w-auto"
            @change="
                filter({
                    template: selectValue($event)
                        ? Number(selectValue($event))
                        : null,
                })
            "
        >
            <option value="">All templates</option>
            <option
                v-for="template in templates"
                :key="template.id"
                :value="template.id"
            >
                {{ template.name }}
            </option>
        </select>
        <select
            :value="filters.status ?? ''"
            aria-label="Filter by status"
            class="cp-input w-auto"
            @change="
                filter({ status: selectValue($event) as PostStatus | null })
            "
        >
            <option value="">Any status</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
        </select>
    </div>

    <section class="cp-card">
        <PostList
            :posts="posts.data"
            :empty="
                filters.template || filters.status
                    ? 'No posts match these filters.'
                    : 'No posts yet.'
            "
        />
    </section>

    <nav
        v-if="posts.last_page > 1"
        class="mt-4 flex items-center justify-between text-sm"
    >
        <Link
            v-if="posts.prev_page_url"
            :href="posts.prev_page_url"
            class="cp-btn"
            preserve-scroll
        >
            &larr; Newer
        </Link>
        <span v-else />
        <span class="text-neutral-500">
            Page {{ posts.current_page }} of {{ posts.last_page }}
        </span>
        <Link
            v-if="posts.next_page_url"
            :href="posts.next_page_url"
            class="cp-btn"
            preserve-scroll
        >
            Older &rarr;
        </Link>
        <span v-else />
    </nav>
</template>
