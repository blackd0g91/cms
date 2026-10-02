<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useWidgets } from '@/composables/useWidgets';
import { requestJson } from '@/lib/http';
import { preview as previewRoute } from '@/routes/cp/markdown';

const emit = defineEmits<{
    insert: [markdown: string];
}>();

type Field = {
    key: string;
    label: string;
    type?: 'text' | 'number' | 'date' | 'time' | 'select';
    placeholder?: string;
    hint?: string;
    optional?: boolean;
    options?: { value: string; label: string }[];
    // A "|" would be read as the start of the label that follows it.
    beforeLabel?: boolean;
};

type Definition = {
    name: string;
    title: string;
    description: string;
    fields: Field[];
    // The part between {{ and }}.
    syntax: (values: Record<string, string>) => string;
};

const withLabel = (main: string, label: string) =>
    label ? `${main} | ${label}` : main;

// The widgets of app/Cms/Widgets, with what each one takes.
const widgets: Definition[] = [
    {
        name: 'timer',
        title: 'Timer',
        description: 'Counts down and rings, like 10 minutes for the rice.',
        fields: [
            {
                key: 'duration',
                label: 'Duration',
                placeholder: '10 (minutes), 1h30, 90s',
                beforeLabel: true,
            },
            {
                key: 'label',
                label: 'Label',
                placeholder: 'Rest the dough',
                optional: true,
            },
        ],
        syntax: (v) => withLabel(`timer:${v.duration}`, v.label),
    },
    {
        name: 'stopwatch',
        title: 'Stopwatch',
        description: 'Start, pause and laps, for steps that take a while.',
        fields: [
            {
                key: 'label',
                label: 'Label',
                placeholder: 'Rest timer',
                optional: true,
            },
        ],
        syntax: (v) => (v.label ? `stopwatch:${v.label}` : 'stopwatch'),
    },
    {
        name: 'temp',
        title: 'Temperature',
        description: 'Shows °C and °F, so a recipe works everywhere.',
        fields: [
            {
                key: 'degrees',
                label: 'Temperature',
                type: 'number',
                placeholder: '180',
            },
            {
                key: 'unit',
                label: 'In',
                type: 'select',
                options: [
                    { value: 'c', label: '°C' },
                    { value: 'f', label: '°F' },
                ],
            },
        ],
        syntax: (v) => `temp:${v.degrees}${v.unit}`,
    },
    {
        name: 'copy',
        title: 'Copy button',
        description: 'Text with a button that copies it, like a command.',
        fields: [
            {
                key: 'text',
                label: 'Text to copy',
                placeholder: 'npm run dev',
                beforeLabel: true,
            },
            {
                key: 'label',
                label: 'Label',
                placeholder: 'Wi-Fi password',
                hint: 'Shown instead of the text, which then stays hidden.',
                optional: true,
            },
        ],
        syntax: (v) => withLabel(`copy:${v.text}`, v.label),
    },
    {
        name: 'qr',
        title: 'QR code',
        description: 'A link or text to scan with a phone.',
        fields: [
            {
                key: 'data',
                label: 'Link or text',
                placeholder: 'https://example.com',
                beforeLabel: true,
            },
            {
                key: 'caption',
                label: 'Caption',
                placeholder: 'Scan to open',
                optional: true,
            },
        ],
        syntax: (v) => withLabel(`qr:${v.data}`, v.caption),
    },
    {
        name: 'youtube',
        title: 'YouTube video',
        description: 'Only loads from YouTube when it is played.',
        fields: [
            {
                key: 'address',
                label: 'Video link',
                placeholder: 'https://www.youtube.com/watch?v=…',
                beforeLabel: true,
            },
            {
                key: 'caption',
                label: 'Caption',
                placeholder: 'How to shape the loaf',
                optional: true,
            },
        ],
        syntax: (v) => withLabel(`youtube:${v.address}`, v.caption),
    },
    {
        name: 'countdown',
        title: 'Countdown',
        description: 'How long until a date, or since it.',
        fields: [
            { key: 'date', label: 'Date', type: 'date' },
            { key: 'time', label: 'Time', type: 'time', optional: true },
            {
                key: 'label',
                label: 'Label',
                placeholder: 'Christmas',
                optional: true,
            },
        ],
        syntax: (v) =>
            withLabel(
                `countdown:${v.date}${v.time ? ` ${v.time}` : ''}`,
                v.label,
            ),
    },
    {
        name: 'spoiler',
        title: 'Spoiler',
        description: 'Hidden until tapped, for answers or endings.',
        fields: [
            { key: 'text', label: 'Hidden text', placeholder: 'The answer' },
        ],
        syntax: (v) => `spoiler:${v.text}`,
    },
];

const dialog = ref<HTMLDialogElement>();
const formRef = ref<HTMLFormElement>();
const chosen = ref<Definition | null>(null);
const values = ref<Record<string, string>>({});
// Text selected in the editor when it was opened, to start the first field with.
let selected = '';

const open = (selection = '') => {
    selected = selection.trim();
    chosen.value = null;
    dialog.value?.showModal();
};

const close = () => dialog.value?.close();

const choose = async (widget: Definition) => {
    chosen.value = widget;
    values.value = Object.fromEntries(
        widget.fields.map((field, i) => [
            field.key,
            field.type === 'select'
                ? (field.options?.[0]?.value ?? '')
                : i === 0 && !field.type
                  ? selected
                  : '',
        ]),
    );

    await nextTick();
    formRef.value?.querySelector<HTMLElement>('input, select')?.focus();
};

const fieldError = (field: Field) => {
    const value = values.value[field.key] ?? '';

    if (/[{}]/.test(value)) {
        return 'This can’t contain { or }.';
    }

    if (field.beforeLabel && value.includes('|')) {
        return 'This can’t contain |, which starts the label.';
    }

    return null;
};

const ready = computed(
    () =>
        chosen.value !== null &&
        chosen.value.fields.every(
            (field) =>
                (field.optional || (values.value[field.key] ?? '') !== '') &&
                !fieldError(field),
        ),
);

const markdown = computed(() => {
    if (!chosen.value) {
        return '';
    }

    const trimmed = Object.fromEntries(
        Object.entries(values.value).map(([key, value]) => [key, value.trim()]),
    );

    return `{{ ${chosen.value.syntax(trimmed)} }}`;
});

// --- Preview, from the same code that renders the site ------------------------

const previewHtml = ref('');
const previewRef = ref<HTMLDivElement>();
// Try it before inserting: the timer counts down, the spoiler opens.
useWidgets(previewRef, previewHtml);
// null while checking or not filled in yet.
const valid = ref<boolean | null>(null);
let latest = 0;
let debounce: ReturnType<typeof setTimeout> | undefined;

watch([markdown, ready], () => {
    clearTimeout(debounce);
    valid.value = null;
    previewHtml.value = '';

    if (!ready.value) {
        return;
    }

    const request = ++latest;
    debounce = setTimeout(async () => {
        try {
            const { html } = await requestJson<{ html: string }>(
                previewRoute().url,
                { method: 'POST', body: { markdown: markdown.value } },
            );

            if (request === latest) {
                // Values a widget doesn't take are left as written.
                valid.value = html.includes('class="widget');
                previewHtml.value = valid.value ? html : '';
            }
        } catch {
            if (request === latest) {
                // Could not check: let it be inserted anyway.
                valid.value = true;
            }
        }
    }, 300);
});

const insert = () => {
    if (!ready.value || valid.value !== true) {
        return;
    }

    emit('insert', markdown.value);
    close();
};

defineExpose({ open });
</script>

<template>
    <dialog
        ref="dialog"
        aria-labelledby="widget-picker-title"
        class="m-auto max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg border border-neutral-200 bg-white p-0 text-neutral-900 backdrop:bg-black/40 open:flex dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100"
        @click.self="close"
        @close="chosen = null"
    >
        <div
            class="flex items-center justify-between gap-3 border-b border-neutral-200 p-4 dark:border-neutral-800"
        >
            <h2 id="widget-picker-title" class="font-semibold">
                <template v-if="chosen">
                    <button
                        type="button"
                        class="mr-1 text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
                        @click="chosen = null"
                    >
                        Widgets
                    </button>
                    <span class="text-neutral-300 dark:text-neutral-700">
                        /
                    </span>
                    {{ chosen.title }}
                </template>
                <template v-else>Insert a widget</template>
            </h2>
            <button type="button" class="cp-btn" @click="close">Close</button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <ul v-if="!chosen" class="grid gap-2 sm:grid-cols-2">
                <li v-for="widget in widgets" :key="widget.name">
                    <button
                        type="button"
                        class="h-full w-full rounded-md border border-neutral-200 p-3 text-left hover:border-neutral-400 dark:border-neutral-800 dark:hover:border-neutral-600"
                        @click="choose(widget)"
                    >
                        <span class="block text-sm font-medium">
                            {{ widget.title }}
                        </span>
                        <span class="block text-xs text-neutral-500">
                            {{ widget.description }}
                        </span>
                    </button>
                </li>
            </ul>

            <form
                v-else
                ref="formRef"
                class="space-y-4"
                @submit.prevent="insert"
            >
                <div
                    v-for="field in chosen.fields"
                    :key="field.key"
                    class="space-y-1.5"
                >
                    <label :for="`widget-${field.key}`" class="cp-label">
                        {{ field.label }}
                        <span
                            v-if="field.optional"
                            class="font-normal text-neutral-500"
                            >(optional)</span
                        >
                    </label>
                    <select
                        v-if="field.type === 'select'"
                        :id="`widget-${field.key}`"
                        v-model="values[field.key]"
                        class="cp-input"
                    >
                        <option
                            v-for="option in field.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <input
                        v-else
                        :id="`widget-${field.key}`"
                        v-model="values[field.key]"
                        :type="field.type ?? 'text'"
                        :step="field.type === 'number' ? 'any' : undefined"
                        :placeholder="field.placeholder"
                        class="cp-input"
                    />
                    <p v-if="fieldError(field)" class="cp-error">
                        {{ fieldError(field) }}
                    </p>
                    <p v-else-if="field.hint" class="text-xs text-neutral-500">
                        {{ field.hint }}
                    </p>
                </div>

                <div
                    class="space-y-2 rounded-md border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <p class="text-xs font-medium text-neutral-500">Preview</p>
                    <p v-if="!ready" class="text-sm text-neutral-500">
                        Fill in the fields to see it.
                    </p>
                    <p
                        v-else-if="valid === null"
                        class="text-sm text-neutral-500"
                    >
                        Checking…
                    </p>
                    <p v-else-if="!valid" class="cp-error">
                        This can’t be shown as a
                        {{ chosen.title.toLowerCase() }}. Check the values.
                    </p>
                    <!-- Rendered by the server's widget code, not user HTML. -->
                    <div
                        v-else
                        ref="previewRef"
                        class="prose prose-sm max-w-none prose-neutral dark:prose-invert [&>*]:my-0"
                        v-html="previewHtml"
                    />
                    <code
                        class="block font-mono text-xs break-all text-neutral-600 dark:text-neutral-400"
                        >{{ markdown }}</code
                    >
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="cp-btn" @click="chosen = null">
                        Back
                    </button>
                    <button
                        type="submit"
                        class="cp-btn-primary"
                        :disabled="!ready || valid !== true"
                    >
                        Insert
                    </button>
                </div>
            </form>
        </div>
    </dialog>
</template>
