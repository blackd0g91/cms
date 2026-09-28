<script setup lang="ts">
import { ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import { preview as previewRoute } from '@/routes/cp/markdown';

defineProps<{
    id: string;
}>();

const model = defineModel<string | null>();

const tab = ref<'write' | 'preview'>('write');
const html = ref('');
const loading = ref(false);
const error = ref<string | null>(null);

const xsrfToken = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );

const renderPreview = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(previewRoute().url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ markdown: model.value ?? '' }),
        });

        if (!response.ok) {
            throw new Error(`Preview failed (${response.status})`);
        }

        html.value = ((await response.json()) as { html: string }).html;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Preview failed';
    } finally {
        loading.value = false;
    }
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
        <div class="mb-2 flex gap-1" role="tablist">
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
        </div>

        <textarea
            v-show="tab === 'write'"
            :id="id"
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
