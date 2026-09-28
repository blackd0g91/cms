<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { create, edit } from '@/routes/cp/templates';
import { index as postsIndex } from '@/routes/cp/posts';
import type { Template } from '@/types';

defineOptions({ layout: CpLayout });

defineProps<{
    templates: (Pick<Template, 'id' | 'name' | 'handle' | 'description'> & {
        posts_count: number;
    })[];
}>();
</script>

<template>
    <Head title="Templates" />
    <PageHeader title="Templates">
        <Link :href="create()" class="cp-btn-primary">New template</Link>
    </PageHeader>

    <p
        v-if="templates.length === 0"
        class="cp-card p-6 text-sm text-neutral-500"
    >
        No templates yet. A template defines the fields a post has and how it is
        displayed.
    </p>

    <ul
        v-else
        class="cp-card divide-y divide-neutral-200 dark:divide-neutral-800"
    >
        <li
            v-for="template in templates"
            :key="template.id"
            class="flex flex-wrap items-center justify-between gap-3 p-4"
        >
            <div class="min-w-0">
                <Link
                    :href="edit(template.id)"
                    class="font-medium hover:underline"
                >
                    {{ template.name }}
                </Link>
                <p class="text-sm text-neutral-500">
                    /{{ template.handle }} &middot;
                    {{ template.posts_count }}
                    {{ template.posts_count === 1 ? 'post' : 'posts' }}
                </p>
                <p
                    v-if="template.description"
                    class="mt-1 truncate text-sm text-neutral-600 dark:text-neutral-400"
                >
                    {{ template.description }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    :href="postsIndex({ query: { template: template.id } })"
                    class="cp-btn"
                    >Posts</Link
                >
                <Link :href="edit(template.id)" class="cp-btn">Edit</Link>
            </div>
        </li>
    </ul>
</template>
