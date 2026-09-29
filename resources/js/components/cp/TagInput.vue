<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    id: string;
    // Existing tags, suggested while typing.
    suggestions: string[];
}>();

const model = defineModel<string[]>({ required: true });

const draft = ref('');
const input = ref<HTMLInputElement>();

const normalize = (name: string) => name.trim().replace(/\s+/g, ' ');
const same = (a: string, b: string) =>
    a.localeCompare(b, undefined, { sensitivity: 'base' }) === 0;

const available = computed(() =>
    props.suggestions.filter(
        (name) => !model.value.some((tag) => same(tag, name)),
    ),
);

const add = (raw = draft.value) => {
    // Collect first and assign once: the model only updates after the parent
    // has handled the change, so assigning in the loop would drop tags.
    const tags = [...model.value];

    // Several at once: "vegan, quick" adds two tags.
    for (const part of raw.split(',')) {
        const name = normalize(part);

        if (name && !tags.some((tag) => same(tag, name))) {
            // Prefer the existing spelling, so "Git" reuses the tag "git".
            tags.push(props.suggestions.find((tag) => same(tag, name)) ?? name);
        }
    }

    if (tags.length !== model.value.length) {
        model.value = tags;
    }

    draft.value = '';
};

const remove = (index: number) => {
    model.value = model.value.filter((_, i) => i !== index);
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        add();
    } else if (event.key === 'Backspace' && draft.value === '') {
        remove(model.value.length - 1);
    }
};
</script>

<template>
    <div
        class="cp-input flex min-h-10 flex-wrap items-center gap-1.5 py-1.5"
        @click="input?.focus()"
    >
        <span
            v-for="(tag, i) in model"
            :key="tag"
            class="inline-flex items-center gap-1 rounded-full bg-neutral-100 py-0.5 pr-1 pl-2.5 text-xs dark:bg-neutral-800"
        >
            {{ tag }}
            <button
                type="button"
                class="rounded-full px-1 text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100"
                :aria-label="`Remove tag ${tag}`"
                @click.stop="remove(i)"
            >
                &times;
            </button>
        </span>
        <input
            :id="id"
            ref="input"
            v-model="draft"
            type="text"
            :list="`${id}-suggestions`"
            :placeholder="model.length ? '' : 'Add tags…'"
            class="min-w-24 flex-1 bg-transparent text-sm outline-none"
            @keydown="onKeydown"
            @blur="add()"
            @change="add()"
        />
        <datalist :id="`${id}-suggestions`">
            <option v-for="name in available" :key="name" :value="name" />
        </datalist>
    </div>
</template>
