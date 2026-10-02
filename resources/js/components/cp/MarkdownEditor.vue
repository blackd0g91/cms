<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import EmojiPicker from '@/components/cp/EmojiPicker.vue';
import MarkdownHelp from '@/components/cp/MarkdownHelp.vue';
import MediaPicker from '@/components/cp/MediaPicker.vue';
import WidgetPicker from '@/components/cp/WidgetPicker.vue';
import { useWidgets } from '@/composables/useWidgets';
import { requestJson } from '@/lib/http';
import { IMAGE_TYPES, uploadImage } from '@/lib/media';
import { cn } from '@/lib/utils';
import { preview as previewRoute } from '@/routes/cp/markdown';
import type { Media } from '@/types';

defineProps<{
    id: string;
}>();

const model = defineModel<string | null>();

type Mode = 'write' | 'split' | 'preview';

const MODE_KEY = 'cp.markdownEditor.mode';

// The last mode used is remembered in this browser. Split needs room, so
// small screens start with Write.
const initialMode = (): Mode => {
    try {
        const saved = localStorage.getItem(MODE_KEY);

        if (saved === 'write' || saved === 'split' || saved === 'preview') {
            return saved;
        }
    } catch {
        // Storage can be unavailable (private windows); use the default.
    }

    return window.innerWidth >= 1280 ? 'split' : 'write';
};

const mode = ref<Mode>(initialMode());
const fullscreen = ref(false);
const html = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const textareaRef = ref<HTMLTextAreaElement>();
const previewRef = ref<HTMLDivElement>();
const previewContentRef = ref<HTMLDivElement>();
const picker = ref<InstanceType<typeof MediaPicker>>();
const widgetPicker = ref<InstanceType<typeof WidgetPicker>>();

const showsPreview = computed(() => mode.value !== 'write');

// Timers, spoilers and the other widgets work in the preview too.
useWidgets(previewContentRef, html);

// --- Preview ------------------------------------------------------------

let latestRequest = 0;
let debounce: ReturnType<typeof setTimeout> | undefined;

const renderPreview = async () => {
    const request = ++latestRequest;
    loading.value = html.value === '';
    error.value = null;

    try {
        const result = await requestJson<{ html: string }>(previewRoute().url, {
            method: 'POST',
            body: { markdown: model.value ?? '' },
        });

        // Ignore answers to older requests that arrive late.
        if (request === latestRequest) {
            html.value = result.html;
        }
    } catch (e) {
        if (request === latestRequest) {
            error.value = e instanceof Error ? e.message : 'Preview failed';
        }
    } finally {
        if (request === latestRequest) {
            loading.value = false;
        }
    }
};

watch(model, () => {
    if (showsPreview.value) {
        clearTimeout(debounce);
        debounce = setTimeout(() => void renderPreview(), 350);
    }
});

watch(
    mode,
    (value) => {
        try {
            localStorage.setItem(MODE_KEY, value);
        } catch {
            // Not remembering the mode is fine.
        }

        if (value !== 'write') {
            void renderPreview();
        }
    },
    { immediate: true },
);

// Keep the preview roughly at the same place as the text being edited.
const syncScroll = () => {
    const textarea = textareaRef.value;
    const preview = previewRef.value;

    if (!textarea || !preview || mode.value !== 'split') {
        return;
    }

    const ratio =
        textarea.scrollTop /
        Math.max(1, textarea.scrollHeight - textarea.clientHeight);
    preview.scrollTop = ratio * (preview.scrollHeight - preview.clientHeight);
};

// --- Editing --------------------------------------------------------------

/**
 * Replace the selection (or the text between start and end) and select the
 * given part of the new text, so typing continues naturally.
 */
const replace = (
    text: string,
    select: [number, number] = [text.length, text.length],
    start?: number,
    end?: number,
) => {
    const textarea = textareaRef.value;

    if (!textarea) {
        return;
    }

    if (mode.value === 'preview') {
        mode.value = 'split';
    }

    const from = start ?? textarea.selectionStart;
    textarea.focus();
    textarea.setRangeText(text, from, end ?? textarea.selectionEnd, 'end');
    textarea.setSelectionRange(from + select[0], from + select[1]);
    model.value = textarea.value;
};

const selection = () => {
    const textarea = textareaRef.value;

    return textarea
        ? textarea.value.slice(textarea.selectionStart, textarea.selectionEnd)
        : '';
};

/** Surround the selection, e.g. **bold**. */
const wrap = (before: string, after: string, placeholder: string) => {
    const text = selection() || placeholder;

    replace(`${before}${text}${after}`, [
        before.length,
        before.length + text.length,
    ]);
};

/** Put a prefix before every selected line, e.g. "- " for a list. */
const prefixLines = (prefix: (index: number) => string) => {
    const textarea = textareaRef.value;

    if (!textarea) {
        return;
    }

    const { value, selectionStart, selectionEnd } = textarea;
    const start = value.lastIndexOf('\n', selectionStart - 1) + 1;
    const lineEnd = value.indexOf('\n', selectionEnd);
    const end = lineEnd === -1 ? value.length : lineEnd;

    const lines = value
        .slice(start, end)
        .split('\n')
        .map((line, index) => prefix(index) + line);
    const text = lines.join('\n');

    replace(text, [text.length, text.length], start, end);
};

const codeBlock = () => {
    const code = selection() || 'code';
    const before = '```\n';

    replace(`${before}${code}\n\`\`\``, [
        before.length,
        before.length + code.length,
    ]);
};

const link = () => {
    const text = selection() || 'link text';
    const markdown = `[${text}](https://)`;

    // Select the URL part, ready to paste over.
    replace(markdown, [text.length + 3, markdown.length - 1]);
};

const imageMarkdown = (media: Media) =>
    `![${(media.alt ?? media.filename).replace(/[[\]]/g, '')}](${media.url})`;

const insertImage = (media: Media) => replace(imageMarkdown(media));

// --- Pasting and dropping images ---------------------------------------------

const uploadError = ref<string | null>(null);
const dragging = ref(false);
let uploads = 0;

/**
 * Put text where a placeholder is, keeping the cursor where it was (it may
 * have moved on while uploading). Nothing happens if the placeholder was
 * deleted meanwhile.
 */
const swap = (placeholder: string, text: string) => {
    const textarea = textareaRef.value;
    const at = textarea?.value.indexOf(placeholder) ?? -1;

    if (!textarea || at === -1) {
        return;
    }

    textarea.setRangeText(text, at, at + placeholder.length, 'preserve');
    model.value = textarea.value;
};

/**
 * Upload images into the media library and add them at the cursor (or at
 * the end, when nothing in the text was clicked). Each gets a placeholder
 * right away, replaced once it is uploaded, so writing can go on.
 */
const addImages = async (files: File[]) => {
    const textarea = textareaRef.value;
    const images = files.filter((file) => IMAGE_TYPES.includes(file.type));
    const others = files.filter((file) => !images.includes(file));

    uploadError.value = others.length
        ? `Only images can be added here, not ${others.map((file) => file.name).join(', ')}.`
        : null;

    if (!textarea || !images.length) {
        return;
    }

    let before = '';

    if (document.activeElement !== textarea) {
        textarea.setSelectionRange(
            textarea.value.length,
            textarea.value.length,
        );
        before =
            textarea.value === '' || textarea.value.endsWith('\n')
                ? ''
                : '\n\n';
    }

    const placeholders = images.map(
        (file) =>
            `[Uploading ${file.name.replace(/[[\]]/g, '')}…](#uploading-${++uploads})`,
    );
    replace(before + placeholders.join('\n'));

    // One at a time, as each one is resized on the server.
    for (const [i, file] of images.entries()) {
        try {
            swap(placeholders[i], imageMarkdown(await uploadImage(file)));
        } catch (e) {
            swap(placeholders[i], '');
            uploadError.value = `${file.name} was not added: ${e instanceof Error ? e.message : 'the upload failed'}`;
        }
    }
};

const onPaste = (event: ClipboardEvent) => {
    const data = event.clipboardData;
    const files = [...(data?.files ?? [])];

    // Text copied from documents often comes with a picture of it too, so
    // only clipboards without text are uploaded.
    if (!files.length || data?.types.includes('text/plain')) {
        return;
    }

    event.preventDefault();
    void addImages(files);
};

const draggingFiles = (event: DragEvent) =>
    event.dataTransfer?.types.includes('Files') ?? false;

const onDragOver = (event: DragEvent) => {
    // Text dragged around inside the editor moves as usual.
    if (draggingFiles(event)) {
        event.preventDefault();
        dragging.value = true;
    }
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;

    if (draggingFiles(event)) {
        // Otherwise the browser would open the file instead of the page.
        event.preventDefault();
        void addImages([...(event.dataTransfer?.files ?? [])]);
    }
};

type Tool = {
    label: string;
    title: string;
    run: () => void;
    class?: string;
};

const tools: Tool[][] = [
    [
        {
            label: 'B',
            title: 'Bold (Ctrl+B)',
            run: () => wrap('**', '**', 'bold text'),
            class: 'font-bold',
        },
        {
            label: 'I',
            title: 'Italic (Ctrl+I)',
            run: () => wrap('_', '_', 'italic text'),
            class: 'italic font-serif',
        },
        {
            label: 'H2',
            title: 'Heading',
            run: () => prefixLines(() => '## '),
        },
        {
            label: 'H3',
            title: 'Subheading',
            run: () => prefixLines(() => '### '),
        },
    ],
    [
        { label: 'Link', title: 'Link (Ctrl+K)', run: link },
        {
            label: '`code`',
            title: 'Inline code',
            run: () => wrap('`', '`', 'code'),
            class: 'font-mono',
        },
        {
            label: '```',
            title: 'Code block',
            run: codeBlock,
            class: 'font-mono',
        },
    ],
    [
        {
            label: '• List',
            title: 'Bulleted list',
            run: () => prefixLines(() => '- '),
        },
        {
            label: '1. List',
            title: 'Numbered list',
            run: () => prefixLines((index) => `${index + 1}. `),
        },
        {
            label: '❝ Quote',
            title: 'Quote',
            run: () => prefixLines(() => '> '),
        },
        {
            label: 'Image',
            title: 'Insert image (or paste or drop one into the text)',
            run: () => picker.value?.open(),
        },
        {
            label: 'Widget',
            title: 'Insert a widget: timer, QR code, video…',
            // Selected text becomes the widget's value.
            run: () => widgetPicker.value?.open(selection()),
        },
    ],
];

const onKeydown = (event: KeyboardEvent) => {
    if (
        event.key === 'Tab' &&
        !event.shiftKey &&
        !event.ctrlKey &&
        !event.altKey
    ) {
        // Indent instead of leaving the editor, which matters for code.
        // Shift+Tab still moves focus on.
        event.preventDefault();
        replace('    ');

        return;
    }

    if (event.key === 'Escape' && fullscreen.value) {
        fullscreen.value = false;

        return;
    }

    if (!(event.ctrlKey || event.metaKey) || event.altKey) {
        return;
    }

    const shortcut = {
        b: () => wrap('**', '**', 'bold text'),
        i: () => wrap('_', '_', 'italic text'),
        k: link,
    }[event.key.toLowerCase()];

    if (shortcut) {
        event.preventDefault();
        shortcut();
    }
};

// --- Full screen ----------------------------------------------------------

watch(fullscreen, async (value) => {
    document.body.style.overflow = value ? 'hidden' : '';

    if (value && mode.value === 'write') {
        mode.value = 'split';
    }

    await nextTick();
    textareaRef.value?.focus();
});

onUnmounted(() => {
    clearTimeout(debounce);
    document.body.style.overflow = '';
});

const modeClass = (name: Mode) =>
    cn(
        'rounded-md px-2.5 py-1 text-sm transition-colors',
        mode.value === name
            ? 'bg-white font-medium text-neutral-900 shadow-xs ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-100 dark:ring-neutral-700'
            : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200',
    );
</script>

<template>
    <div
        :class="
            cn(
                fullscreen &&
                    'fixed inset-0 z-50 flex flex-col bg-white p-4 dark:bg-neutral-950',
            )
        "
    >
        <div
            class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div
                v-for="(group, i) in tools"
                :key="i"
                class="flex items-center gap-0.5"
            >
                <button
                    v-for="tool in group"
                    :key="tool.title"
                    type="button"
                    :title="tool.title"
                    :aria-label="tool.title"
                    :class="
                        cn(
                            'rounded-md px-2 py-1 text-xs text-neutral-600 transition-colors hover:bg-white hover:text-neutral-900 hover:shadow-xs dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100',
                            tool.class,
                        )
                    "
                    @mousedown.prevent
                    @click="tool.run"
                >
                    {{ tool.label }}
                </button>
            </div>

            <EmojiPicker @select="replace($event)" />
            <MarkdownHelp />

            <div class="ml-auto flex items-center gap-1" role="tablist">
                <button
                    v-for="option in ['write', 'split', 'preview'] as const"
                    :key="option"
                    type="button"
                    role="tab"
                    :aria-selected="mode === option"
                    :class="modeClass(option)"
                    @click="mode = option"
                >
                    {{ option.charAt(0).toUpperCase() + option.slice(1) }}
                </button>
                <button
                    type="button"
                    :title="
                        fullscreen ? 'Exit full screen (Esc)' : 'Full screen'
                    "
                    class="ml-1 rounded-md px-2.5 py-1 text-sm text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
                    @click="fullscreen = !fullscreen"
                >
                    {{ fullscreen ? 'Exit full screen' : 'Full screen' }}
                </button>
            </div>
        </div>
        <MediaPicker ref="picker" @select="insertImage" />
        <WidgetPicker ref="widgetPicker" @insert="replace($event)" />

        <p
            v-if="uploadError"
            class="mb-2 flex items-start justify-between gap-3 text-sm text-red-600 dark:text-red-400"
            role="alert"
        >
            {{ uploadError }}
            <button
                type="button"
                class="shrink-0 text-xs text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
                @click="uploadError = null"
            >
                Dismiss
            </button>
        </p>

        <div
            :class="
                cn(
                    'grid gap-3',
                    mode === 'split' && 'lg:grid-cols-2',
                    fullscreen
                        ? 'min-h-0 flex-1'
                        : mode === 'split' && 'h-[32rem]',
                )
            "
        >
            <textarea
                v-show="mode !== 'preview'"
                :id="id"
                ref="textareaRef"
                :value="model ?? ''"
                :rows="mode === 'write' && !fullscreen ? 14 : undefined"
                spellcheck="false"
                placeholder="Write in markdown. Paste or drop images to add them."
                :class="
                    cn(
                        'cp-input font-mono',
                        (mode === 'split' || fullscreen) &&
                            'h-full resize-none',
                        dragging && 'border-brand ring-3 ring-brand/20',
                    )
                "
                @input="model = ($event.target as HTMLTextAreaElement).value"
                @keydown="onKeydown"
                @scroll="syncScroll"
                @paste="onPaste"
                @dragover="onDragOver"
                @dragleave="dragging = false"
                @drop="onDrop"
            />

            <div
                v-if="showsPreview"
                ref="previewRef"
                :class="
                    cn(
                        'overflow-y-auto rounded-lg border border-neutral-200 p-4 dark:border-neutral-800',
                        mode === 'split' || fullscreen ? 'h-full' : 'min-h-40',
                    )
                "
            >
                <p v-if="loading" class="text-sm text-neutral-500">
                    Rendering…
                </p>
                <p v-else-if="error" class="cp-error">{{ error }}</p>
                <p v-else-if="!html" class="text-sm text-neutral-500">
                    Nothing to preview.
                </p>
                <div
                    v-else
                    ref="previewContentRef"
                    class="prose max-w-none prose-neutral dark:prose-invert"
                    v-html="html"
                />
            </div>
        </div>

        <p v-if="fullscreen" class="mt-2 text-xs text-neutral-500">
            Esc to exit full screen &middot; Ctrl+S to save
        </p>
    </div>
</template>
