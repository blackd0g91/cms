<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { destroy, update } from '@/routes/cp/tags';

defineOptions({ layout: CpLayout });

defineProps<{
    tags: { id: number; name: string; slug: string; posts_count: number }[];
}>();

const rename = (tag: { id: number; name: string }, name: string) => {
    if (name.trim() === '' || name === tag.name) {
        return;
    }

    router.put(update(tag.id).url, { name }, { preserveScroll: true });
};

const remove = (tag: { id: number; name: string; posts_count: number }) => {
    const posts = tag.posts_count === 1 ? '1 post' : `${tag.posts_count} posts`;

    if (
        confirm(
            tag.posts_count
                ? `Delete "${tag.name}"? It will be removed from ${posts}.`
                : `Delete "${tag.name}"?`,
        )
    ) {
        router.delete(destroy(tag.id).url, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Tags" />
    <PageHeader title="Tags" />

    <p class="mb-6 text-sm text-neutral-500">
        Tags are added while editing posts. Rename or delete them here.
    </p>

    <p v-if="tags.length === 0" class="cp-card p-6 text-sm text-neutral-500">
        No tags yet.
    </p>

    <ul
        v-else
        class="cp-card max-w-2xl divide-y divide-neutral-200 dark:divide-neutral-800"
    >
        <li
            v-for="tag in tags"
            :key="tag.id"
            class="flex flex-wrap items-center gap-3 p-3"
        >
            <input
                type="text"
                :value="tag.name"
                :aria-label="`Name of tag ${tag.name}`"
                class="cp-input w-auto flex-1"
                @change="rename(tag, ($event.target as HTMLInputElement).value)"
            />
            <span class="w-20 text-right text-xs text-neutral-500">
                {{ tag.posts_count }}
                {{ tag.posts_count === 1 ? 'post' : 'posts' }}
            </span>
            <a :href="`/tags/${tag.slug}`" target="_blank" class="cp-btn">
                View
            </a>
            <button type="button" class="cp-btn-danger" @click="remove(tag)">
                Delete
            </button>
            <p
                v-if="$page.props.errors[`name.${tag.id}`]"
                class="cp-error w-full"
            >
                {{ $page.props.errors[`name.${tag.id}`] }}
            </p>
        </li>
    </ul>
</template>
