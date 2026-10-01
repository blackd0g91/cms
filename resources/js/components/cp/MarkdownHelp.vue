<script setup lang="ts">
import { ref } from 'vue';

const dialog = ref<HTMLDialogElement>();

type Row = {
    // What to type.
    type: string;
    // How it looks, as the site renders it. Fixed strings, not user input.
    looks: string;
};

const sections: { title: string; rows: Row[] }[] = [
    {
        title: 'Text',
        rows: [
            {
                type: '**bold** and _italic_',
                looks: '<strong>bold</strong> and <em>italic</em>',
            },
            { type: '~~struck through~~', looks: '<del>struck through</del>' },
            { type: '==highlighted==', looks: '<mark>highlighted</mark>' },
            {
                type: 'H~2~O and x^2^',
                looks: 'H<sub>2</sub>O and x<sup>2</sup>',
            },
            { type: '`inline code`', looks: '<code>inline code</code>' },
            {
                type: '[a link](https://example.com)',
                looks: '<a href="https://example.com" target="_blank" rel="noopener">a link</a>',
            },
            { type: ':tada: :pizza: :+1:', looks: '🎉 🍕 👍' },
        ],
    },
    {
        title: 'Keys',
        rows: [
            {
                type: 'Press [[Ctrl]]+[[C]]',
                looks: 'Press <kbd>Ctrl</kbd>+<kbd>C</kbd>',
            },
            {
                type: '[[Ctrl+Shift+P]]',
                looks: '<span class="keys"><kbd>Ctrl</kbd><span class="keys-plus">+</span><kbd>Shift</kbd><span class="keys-plus">+</span><kbd>P</kbd></span>',
            },
        ],
    },
    {
        title: 'Headings',
        rows: [
            {
                type: '## Section\n### Subsection',
                looks: '<h3 class="!mt-0">Section</h3><h4 class="!mb-0">Subsection</h4>',
            },
            {
                type: '## Setup {#install}',
                looks: 'A heading you can always link to as <code>#install</code>, even if it is renamed',
            },
        ],
    },
    {
        title: 'Lists',
        rows: [
            {
                type: '- Flour\n- Eggs',
                looks: '<ul class="!my-0"><li>Flour</li><li>Eggs</li></ul>',
            },
            {
                type: '1. Mix\n2. Bake',
                looks: '<ol class="!my-0"><li>Mix</li><li>Bake</li></ol>',
            },
            {
                type: '- [x] Done\n- [ ] To do',
                looks: '<ul class="!my-0 list-none !pl-0"><li><input type="checkbox" checked disabled> Done</li><li><input type="checkbox" disabled> To do</li></ul>',
            },
            {
                type: 'Term\n: Its definition',
                looks: '<dl class="!my-0"><dt class="!mt-0">Term</dt><dd>Its definition</dd></dl>',
            },
        ],
    },
    {
        title: 'Blocks',
        rows: [
            {
                type: '> A quote',
                looks: '<blockquote class="!my-0"><p>A quote</p></blockquote>',
            },
            {
                type: '```bash\ngit status\n```',
                looks: 'A code block, highlighted for the language named after the backticks (bash, php, js, sql…)',
            },
            {
                type: '| Key | Does |\n| --- | --- |\n| Esc | Close |',
                looks: '<table class="!my-0"><thead><tr><th>Key</th><th>Does</th></tr></thead><tbody><tr><td>Esc</td><td>Close</td></tr></tbody></table>',
            },
            { type: '---', looks: 'A dividing line' },
        ],
    },
    {
        title: 'Callouts',
        rows: [
            {
                type: '> [!TIP]\n> Salt the water.',
                looks: '<div class="callout callout-tip !my-0"><p class="callout-title">Tip</p><div class="callout-body"><p>Salt the water.</p></div></div>',
            },
            {
                type: '> [!WARNING] Hot oil\n> Keep the lid near.',
                looks: 'Also <code>[!NOTE]</code>, <code>[!IMPORTANT]</code> and <code>[!CAUTION]</code>, with an optional title after the marker',
            },
            {
                type: '> [!NOTE]- Why it works\n> The starch thickens it.',
                looks: 'Collapsible: <code>-</code> starts closed, <code>+</code> starts open',
            },
            {
                type: '> [!DETAILS] Full list\n> Everything else.',
                looks: '<details class="callout callout-details !my-0"><summary class="callout-title">Full list</summary><div class="callout-body"><p>Everything else.</p></div></details>',
            },
        ],
    },
    {
        title: 'Widgets',
        rows: [
            {
                type: '{{ qr:https://example.com }}',
                looks: 'A QR code to scan with a phone. Any text works, like a Wi-Fi password: <code>{{ qr:WIFI:S:Home;T:WPA;P:secret;; | Home Wi-Fi }}</code>, with a caption after <code>|</code>',
            },
        ],
    },
    {
        title: 'Footnotes',
        rows: [
            {
                type: 'A claim[^1].\n\n[^1]: The source.',
                looks: 'A claim<sup>1</sup>, with the note listed at the end of the post',
            },
        ],
    },
];

const shortcuts = [
    ['Ctrl+B', 'Bold'],
    ['Ctrl+I', 'Italic'],
    ['Ctrl+K', 'Link'],
    ['Tab', 'Indent'],
    ['Ctrl+S', 'Save'],
];

const open = () => dialog.value?.showModal();
const close = () => dialog.value?.close();
</script>

<template>
    <button
        type="button"
        title="Markdown help"
        aria-label="Markdown help"
        class="rounded px-2 py-1 text-xs font-medium text-neutral-600 hover:bg-white hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
        @mousedown.prevent
        @click="open"
    >
        ?
    </button>

    <dialog
        ref="dialog"
        aria-labelledby="markdown-help-title"
        class="m-auto max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-lg border border-neutral-200 bg-white p-0 text-neutral-900 backdrop:bg-black/40 open:flex dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100"
        @click.self="close"
    >
        <div
            class="flex items-center justify-between gap-3 border-b border-neutral-200 p-4 dark:border-neutral-800"
        >
            <h2 id="markdown-help-title" class="font-semibold">
                Writing in markdown
            </h2>
            <button type="button" class="cp-btn" @click="close">Close</button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <section
                v-for="section in sections"
                :key="section.title"
                class="mb-5"
            >
                <h3
                    class="mb-2 text-xs font-medium tracking-wide text-neutral-500 uppercase"
                >
                    {{ section.title }}
                </h3>
                <table class="w-full text-sm">
                    <thead class="sr-only">
                        <tr>
                            <th>You type</th>
                            <th>You get</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-neutral-200 dark:divide-neutral-800"
                    >
                        <tr v-for="row in section.rows" :key="row.type">
                            <td class="w-1/2 py-2 pr-4 align-top">
                                <pre
                                    class="rounded-md bg-neutral-100 px-2 py-1.5 font-mono text-xs whitespace-pre-wrap dark:bg-neutral-800"
                                    >{{ row.type }}</pre>
                            </td>
                            <td class="py-2 align-top">
                                <div
                                    class="prose prose-sm max-w-none prose-neutral dark:prose-invert [&>*:first-child]:mt-0"
                                    v-html="row.looks"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section>
                <h3
                    class="mb-2 text-xs font-medium tracking-wide text-neutral-500 uppercase"
                >
                    Shortcuts
                </h3>
                <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    <li v-for="[keys, action] in shortcuts" :key="keys">
                        <kbd
                            class="rounded border border-neutral-300 px-1.5 py-0.5 font-mono text-xs dark:border-neutral-700"
                            >{{ keys }}</kbd
                        >
                        {{ action }}
                    </li>
                </ul>
                <p class="mt-3 text-xs text-neutral-500">
                    The 😊 button finds emoji by name, and hovering one shows
                    its :shortcode:. Raw HTML works too.
                </p>
            </section>
        </div>
    </dialog>
</template>
