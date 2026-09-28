<script setup lang="ts">
import { ref, watch } from 'vue';
import MediaPicker from '@/components/cp/MediaPicker.vue';
import { requestJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import { preview as previewRoute } from '@/routes/cp/markdown';
import type { Media } from '@/types';

defineProps<{
    id: string;
}>();

const model = defineModel<string | null>();

const tab = ref<'write' | 'preview'>('write');
const html = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const textareaRef = ref<HTMLTextAreaElement>();
const picker = ref<InstanceType<typeof MediaPicker>>();

const renderPreview = async () => {
    loading.value = true;
    error.value = null;

    try {
        html.value = (
            await requestJson<{ html: string }>(previewRoute().url, {
                method: 'POST',
                body: { markdown: model.value ?? '' },
            })
        ).html;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Preview failed';
    } finally {
        loading.value = false;
    }
};

const insertImage = (media: Media) => {
    const textarea = textareaRef.value;
    const alt = (media.alt ?? media.filename).replace(/[[\]]/g, '');
    const markdown = `![${alt}](${media.url})`;

    tab.value = 'write';

    if (!textarea) {
        model.value = `${model.value ?? ''}${markdown}`;

        return;
    }

    textarea.setRangeText(
        markdown,
        textarea.selectionStart,
        textarea.selectionEnd,
        'end',
    );
    model.value = textarea.value;
    textarea.focus();
};

watch(tab, (value) => {
    if (value === 'preview') {
        void renderPreview();
    }
});

// Insert indentation instead of moving focus, which matters when writing code.
// Shift+Tab still moves focus out of the editor.
const onTab = (event: KeyboardEvent) => {
    const textarea = event.target as HTMLTextAreaElement;

    textarea.setRangeText(
        '    ',
        textarea.selectionStart,
        textarea.selectionEnd,
        'end',
    );
    model.value = textarea.value;
};

const tabClass = (name: typeof tab.value) =>
    cn(
        'rounded-md px-3 py-1 text-sm',
        tab.value === name
            ? 'bg-neutral-100 font-medium dark:bg-neutral-800'
            : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200',
    );
</script>

<template>
    <div>
        <div class="mb-2 flex items-center gap-1" role="tablist">
            <button
                type="button"
                role="tab"
                :aria-selected="tab === 'write'"
                :class="tabClass('write')"
                @click="tab = 'write'"
            >
                Write
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="tab === 'preview'"
                :class="tabClass('preview')"
                @click="tab = 'preview'"
            >
                Preview
            </button>
            <button
                type="button"
                class="ml-auto rounded-md px-3 py-1 text-sm text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
                @click="picker?.open()"
            >
                Insert image
            </button>
        </div>
        <MediaPicker ref="picker" @select="insertImage" />

        <textarea
            v-show="tab === 'write'"
            :id="id"
            ref="textareaRef"
            :value="model ?? ''"
            rows="14"
            spellcheck="false"
            class="cp-input font-mono"
            @input="model = ($event.target as HTMLTextAreaElement).value"
            @keydown.tab.exact.prevent="onTab"
        />

        <div
            v-if="tab === 'preview'"
            class="min-h-40 rounded-md border border-neutral-200 p-4 dark:border-neutral-800"
        >
            <p v-if="loading" class="text-sm text-neutral-500">Rendering…</p>
            <p v-else-if="error" class="cp-error">{{ error }}</p>
            <p v-else-if="!html" class="text-sm text-neutral-500">
                Nothing to preview.
            </p>
            <div
                v-else
                class="prose max-w-none prose-neutral dark:prose-invert"
                v-html="html"
            />
        </div>
    </div>
</template>
