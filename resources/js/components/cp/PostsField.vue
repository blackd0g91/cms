<script setup lang="ts">
import { computed } from 'vue';
import type { PostChoices } from '@/types';

const props = defineProps<{
    id: string;
    // Every post, by template.
    choices: PostChoices[];
    // The post being edited, which can not link to itself.
    postId?: number | null;
}>();

// Post ids, in the order they are shown.
const model = defineModel<number[] | null>();

const ids = computed(() => model.value ?? []);

const posts = computed(
    () =>
        new Map(
            props.choices.flatMap((template) =>
                template.posts.map((post) => [
                    post.id,
                    { ...post, template: template.name },
                ]),
            ),
        ),
);

const add = (event: Event) => {
    const select = event.target as HTMLSelectElement;

    if (select.value) {
        model.value = [...ids.value, Number(select.value)];
        select.value = '';
    }
};

const move = (index: number, by: number) => {
    const items = [...ids.value];
    [items[index], items[index + by]] = [items[index + by], items[index]];
    model.value = items;
};

const remove = (index: number) => {
    model.value = ids.value.filter((_, i) => i !== index);
};
</script>

<template>
    <div class="space-y-2">
        <ol v-if="ids.length" class="space-y-2">
            <li
                v-for="(postId, i) in ids"
                :key="postId"
                class="flex items-center gap-2 rounded-lg border border-neutral-200 py-1.5 pr-1.5 pl-3 text-sm dark:border-neutral-800"
            >
                <span class="min-w-0 flex-1 truncate">
                    <template v-if="posts.get(postId)">
                        {{ posts.get(postId)?.title }}
                        <span class="text-neutral-500">
                            &middot; {{ posts.get(postId)?.template }}
                        </span>
                    </template>
                    <span v-else class="text-neutral-500">
                        Post #{{ postId }} (deleted)
                    </span>
                </span>
                <span
                    v-if="posts.get(postId)?.status === 'draft'"
                    class="cp-badge cp-badge-draft"
                    title="Shown once it is published"
                >
                    Draft
                </span>
                <button
                    type="button"
                    class="cp-btn px-2 py-1"
                    :disabled="i === 0"
                    aria-label="Move up"
                    @click="move(i, -1)"
                >
                    &uarr;
                </button>
                <button
                    type="button"
                    class="cp-btn px-2 py-1"
                    :disabled="i === ids.length - 1"
                    aria-label="Move down"
                    @click="move(i, 1)"
                >
                    &darr;
                </button>
                <button
                    type="button"
                    class="cp-btn-danger px-2 py-1"
                    @click="remove(i)"
                >
                    Remove
                </button>
            </li>
        </ol>

        <select :id="id" class="cp-input" @change="add">
            <option value="">Add a post…</option>
            <optgroup
                v-for="template in choices"
                :key="template.id"
                :label="template.name"
            >
                <option
                    v-for="post in template.posts"
                    :key="post.id"
                    :value="post.id"
                    :disabled="ids.includes(post.id) || post.id === postId"
                >
                    {{ post.title
                    }}{{ post.status === 'draft' ? ' (draft)' : '' }}
                </option>
            </optgroup>
        </select>
    </div>
</template>
