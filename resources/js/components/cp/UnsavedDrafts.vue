<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { listLocalDrafts, removeLocalDraft } from '@/composables/useLocalDraft';
import { create, edit } from '@/routes/cp/templates/posts';

const props = defineProps<{
    // Template names by id, to label each draft.
    templates: { id: number; name: string }[];
}>();

type Entry = {
    key: string;
    title: string;
    template: string;
    savedAt: string;
    href: string;
};

const entries = ref<Entry[]>([]);

// Drafts are saved by the post editor as "post.{template}.{post id or new}".
const load = () => {
    entries.value = listLocalDrafts<{ title?: string }>('post.')
        .map(({ key, draft }) => {
            const [, templateId, postId] = key.split('.');
            const template = props.templates.find(
                (t) => t.id === Number(templateId),
            );

            if (!template) {
                return null;
            }

            return {
                key,
                title: draft.data.title?.trim() || 'Untitled',
                template: template.name,
                savedAt: draft.savedAt,
                href:
                    postId === 'new'
                        ? create(template.id).url
                        : edit([template.id, Number(postId)]).url,
            };
        })
        .filter((entry): entry is Entry => entry !== null)
        .sort((a, b) => b.savedAt.localeCompare(a.savedAt));
};

onMounted(load);

const discard = (entry: Entry) => {
    if (confirm(`Discard the unsaved changes to "${entry.title}"?`)) {
        removeLocalDraft(entry.key);
        load();
    }
};

const timeAgo = (iso: string) => {
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    const hours = Math.round(minutes / 60);

    return hours < 48
        ? `${hours} h ago`
        : new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
};
</script>

<template>
    <section
        v-if="entries.length"
        class="cp-card border-amber-300 dark:border-amber-800"
    >
        <h2
            class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 font-semibold dark:border-neutral-800"
        >
            Unsaved changes in this browser
            <span class="text-xs font-normal text-neutral-500">
                Kept by autosave; open a post to restore or discard them.
            </span>
        </h2>
        <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
            <li
                v-for="entry in entries"
                :key="entry.key"
                class="flex items-center justify-between gap-3 px-4 py-3"
            >
                <div class="min-w-0">
                    <Link
                        :href="entry.href"
                        class="block truncate text-sm font-medium hover:underline"
                    >
                        {{ entry.title }}
                    </Link>
                    <p class="text-xs text-neutral-500">
                        {{ entry.template }}
                        <template v-if="entry.key.endsWith('.new')">
                            &middot; new post
                        </template>
                        &middot; {{ timeAgo(entry.savedAt) }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Link :href="entry.href" class="cp-btn">Continue</Link>
                    <button
                        type="button"
                        class="cp-btn-danger"
                        @click="discard(entry)"
                    >
                        Discard
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>
