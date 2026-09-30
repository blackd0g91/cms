<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import SaveButton from '@/components/cp/SaveButton.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import CpLayout from '@/layouts/CpLayout.vue';
import { slugify } from '@/lib/utils';
import {
    destroy,
    duplicate,
    index,
    store,
    update,
} from '@/routes/cp/templates';
import type { Field, FieldType, FieldTypeOption, Template } from '@/types';

defineOptions({ layout: CpLayout });

const props = defineProps<{
    template: Template | null;
    // Only when editing: a template with posts can not be deleted.
    postsCount?: number;
    fieldTypes: FieldTypeOption[];
}>();

type EditableField = Omit<Field, 'options'> & {
    key: number;
    // The handle as last saved, so the server can move post data on rename.
    originalHandle: string | null;
    // The type as last saved, to warn that existing values will be converted.
    originalType: FieldType | null;
    optionsText: string;
    handleTouched: boolean;
};

let nextKey = 0;

const toEditable = (field: Field): EditableField => ({
    handle: field.handle,
    label: field.label,
    type: field.type,
    required: field.required,
    optionsText: field.options.join(', '),
    key: nextKey++,
    originalHandle: field.handle,
    originalType: field.type,
    handleTouched: true,
});

const form = useForm({
    name: props.template?.name ?? '',
    handle: props.template?.handle ?? '',
    description: props.template?.description ?? '',
    color: props.template?.color ?? '',
    fields: (props.template?.fields ?? []).map(toEditable),
    layout: props.template?.layout ?? '',
});

const handleTouched = ref(props.template !== null);
const deleteError = ref<string | null>(null);

const onNameInput = () => {
    if (!handleTouched.value) {
        form.handle = slugify(form.name);
    }
};

const onLabelInput = (field: EditableField) => {
    if (!field.handleTouched) {
        field.handle = slugify(field.label, '_');
    }
};

const addField = () => {
    form.fields.push({
        handle: '',
        label: '',
        type: 'text',
        required: false,
        optionsText: '',
        key: nextKey++,
        originalHandle: null,
        originalType: null,
        handleTouched: false,
    });
};

const moveField = (index: number, offset: number) => {
    const [field] = form.fields.splice(index, 1);
    form.fields.splice(index + offset, 0, field);
};

const removeField = (index: number) => {
    form.fields.splice(index, 1);
};

const fieldError = (index: number, key: string) =>
    form.errors[`fields.${index}.${key}` as keyof typeof form.errors];

const optionsError = (index: number) =>
    Object.entries(form.errors).find(([key]) =>
        key.startsWith(`fields.${index}.options`),
    )?.[1];

const submit = () => {
    form.transform((data) => ({
        ...data,
        fields: data.fields.map((field) => ({
            original_handle: field.originalHandle,
            handle: field.handle,
            label: field.label,
            type: field.type,
            required: field.required,
            options:
                field.type === 'select'
                    ? field.optionsText
                          .split(',')
                          .map((option) => option.trim())
                          .filter(Boolean)
                    : [],
        })),
    })).submit(props.template ? update(props.template.id) : store(), {
        preserveScroll: true,
        // An empty layout is generated on the server, so show the result.
        onSuccess: () => {
            form.layout = props.template?.layout ?? form.layout;
            form.fields.forEach((field) => {
                field.originalHandle = field.handle;
                field.originalType = field.type;
            });
            // The page stays mounted after creating, so the handle is now fixed.
            handleTouched.value = true;
            form.defaults();
        },
    });
};

useUnsavedChanges({
    isDirty: () => form.isDirty,
    save: () => !form.processing && submit(),
});

const deleteTemplate = () => {
    if (
        !props.template ||
        !confirm(`Delete the "${props.template.name}" template?`)
    ) {
        return;
    }

    router.delete(destroy(props.template.id).url, {
        onError: (errors) => {
            deleteError.value = errors.template ?? null;
            // Shown at the top of the page, which may be scrolled away.
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    });
};

const typeLabel = (type: FieldType) =>
    props.fieldTypes.find((option) => option.value === type)?.label ?? type;

const conversionNote = (from: FieldType, to: FieldType) =>
    from === 'image' || to === 'image'
        ? `Existing values can't become ${typeLabel(to)} and will be cleared when you save.`
        : `When you save, existing values are converted from ${typeLabel(from)} to ${typeLabel(to)}. Values that don't fit (like text in a number field) are cleared.`;

const usage = (handle: string, type: FieldType) => {
    switch (type) {
        case 'list':
            return `{{# ${handle} }}<li>{{ . }}</li>{{/ ${handle} }}`;
        case 'boolean':
            return `{{# ${handle} }}...{{/ ${handle} }}`;
        case 'image':
            return `{{ ${handle} }} or {{# ${handle} }}{{ url }} {{ alt }}{{/ ${handle} }}`;
        default:
            return `{{ ${handle} }}`;
    }
};

const variables = computed(() => [
    { name: '{{ title }}', note: 'Post title' },
    { name: '{{ published_at }}', note: 'Publish date' },
    { name: '{{ url }}', note: 'Post URL' },
    { name: '{{ template.name }}', note: 'Template name' },
    ...form.fields
        .filter((field) => field.handle)
        .map((field) => ({
            name: usage(field.handle, field.type),
            note: field.label,
        })),
]);
</script>

<template>
    <Head :title="template ? template.name : 'New template'" />
    <PageHeader
        :title="template ? template.name : 'New template'"
        :crumbs="[{ label: 'Templates', href: index().url }]"
    >
        <template v-if="template">
            <button
                type="button"
                class="cp-btn-danger"
                :disabled="(postsCount ?? 0) > 0"
                :title="
                    postsCount
                        ? `Delete its ${postsCount === 1 ? 'post' : `${postsCount} posts`} first`
                        : 'Delete this template'
                "
                @click="deleteTemplate"
            >
                Delete
            </button>
            <Link
                :href="duplicate(template.id)"
                method="post"
                as="button"
                :preserve-state="false"
                class="cp-btn"
                :disabled="form.isDirty"
                :title="
                    form.isDirty
                        ? 'Save your changes before duplicating'
                        : 'Create a copy of this template, without its posts'
                "
            >
                Duplicate
            </Link>
            <a :href="`/${template.handle}`" target="_blank" class="cp-btn">
                View
            </a>
        </template>
        <SaveButton
            form="template-form"
            :label="template ? 'Save' : 'Create template'"
            :processing="form.processing"
            :dirty="form.isDirty"
            :saved="form.recentlySuccessful"
        />
    </PageHeader>

    <p
        v-if="deleteError"
        class="mb-6 max-w-4xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
        role="alert"
    >
        {{ deleteError }}
    </p>

    <form
        id="template-form"
        class="max-w-4xl space-y-6"
        @submit.prevent="submit"
    >
        <section class="cp-card space-y-4 p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label for="name" class="cp-label">Name</label>
                    <input
                        id="name"
                        v-model="form.name"
                        v-focus="!template"
                        type="text"
                        class="cp-input"
                        @input="onNameInput"
                    />
                    <p v-if="form.errors.name" class="cp-error">
                        {{ form.errors.name }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <label for="handle" class="cp-label">Handle</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-neutral-500">/</span>
                        <input
                            id="handle"
                            v-model="form.handle"
                            type="text"
                            class="cp-input font-mono"
                            @input="handleTouched = true"
                        />
                    </div>
                    <p v-if="form.errors.handle" class="cp-error">
                        {{ form.errors.handle }}
                    </p>
                </div>
            </div>
            <div class="space-y-1.5">
                <label for="description" class="cp-label">Description</label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="2"
                    class="cp-input"
                />
                <p v-if="form.errors.description" class="cp-error">
                    {{ form.errors.description }}
                </p>
            </div>
            <div class="space-y-1.5">
                <label for="color" class="cp-label">Accent color</label>
                <div v-if="form.color" class="flex items-center gap-2">
                    <input
                        id="color"
                        v-model="form.color"
                        type="color"
                        class="h-9 w-12 cursor-pointer rounded-md border border-neutral-300 bg-white p-1 dark:border-neutral-700 dark:bg-neutral-950"
                    />
                    <input
                        v-model="form.color"
                        type="text"
                        aria-label="Accent color hex value"
                        maxlength="7"
                        class="cp-input w-28 font-mono"
                    />
                    <button
                        type="button"
                        class="cp-btn"
                        @click="form.color = ''"
                    >
                        Use automatic
                    </button>
                </div>
                <div v-else class="flex items-center gap-3">
                    <span class="text-sm text-neutral-500">Automatic</span>
                    <button
                        id="color"
                        type="button"
                        class="cp-btn"
                        @click="form.color = '#c2410c'"
                    >
                        Pick a color
                    </button>
                </div>
                <p class="text-xs text-neutral-500">
                    Used for this template's accents on the site: its sidebar
                    dot, tags, links and highlights.
                </p>
                <p v-if="form.errors.color" class="cp-error">
                    {{ form.errors.color }}
                </p>
            </div>
        </section>

        <section class="cp-card p-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold">Fields</h2>
                    <p class="text-sm text-neutral-500">
                        Every post also has a title and a slug.
                    </p>
                </div>
                <button type="button" class="cp-btn" @click="addField">
                    Add field
                </button>
            </div>

            <p v-if="form.fields.length === 0" class="text-sm text-neutral-500">
                No fields yet.
            </p>

            <ol class="space-y-3">
                <li
                    v-for="(field, i) in form.fields"
                    :key="field.key"
                    class="rounded-md border border-neutral-200 p-4 dark:border-neutral-800"
                >
                    <div class="grid gap-3 sm:grid-cols-[1fr_1fr_10rem]">
                        <div class="space-y-1.5">
                            <label
                                :for="`field-${field.key}-label`"
                                class="cp-label"
                            >
                                Label
                            </label>
                            <input
                                :id="`field-${field.key}-label`"
                                v-model="field.label"
                                type="text"
                                class="cp-input"
                                @input="onLabelInput(field)"
                            />
                            <p v-if="fieldError(i, 'label')" class="cp-error">
                                {{ fieldError(i, 'label') }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <label
                                :for="`field-${field.key}-handle`"
                                class="cp-label"
                            >
                                Handle
                            </label>
                            <input
                                :id="`field-${field.key}-handle`"
                                v-model="field.handle"
                                type="text"
                                class="cp-input font-mono"
                                @input="field.handleTouched = true"
                            />
                            <p v-if="fieldError(i, 'handle')" class="cp-error">
                                {{ fieldError(i, 'handle') }}
                            </p>
                            <p
                                v-else-if="
                                    field.originalHandle &&
                                    field.handle !== field.originalHandle
                                "
                                class="text-xs text-neutral-500"
                            >
                                Renamed from
                                <code>{{ field.originalHandle }}</code
                                >. Posts and the layout will be updated on save.
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <label
                                :for="`field-${field.key}-type`"
                                class="cp-label"
                            >
                                Type
                            </label>
                            <select
                                :id="`field-${field.key}-type`"
                                v-model="field.type"
                                class="cp-input"
                            >
                                <option
                                    v-for="type in fieldTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <p
                        v-if="
                            template &&
                            field.originalType &&
                            field.type !== field.originalType
                        "
                        class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                    >
                        {{ conversionNote(field.originalType, field.type) }}
                    </p>

                    <div
                        v-if="field.type === 'select'"
                        class="mt-3 space-y-1.5"
                    >
                        <label
                            :for="`field-${field.key}-options`"
                            class="cp-label"
                        >
                            Options
                        </label>
                        <input
                            :id="`field-${field.key}-options`"
                            v-model="field.optionsText"
                            type="text"
                            placeholder="Easy, Medium, Hard"
                            class="cp-input"
                        />
                        <p v-if="optionsError(i)" class="cp-error">
                            {{ optionsError(i) }}
                        </p>
                    </div>

                    <div
                        class="mt-3 flex flex-wrap items-center justify-between gap-2"
                    >
                        <label
                            v-if="field.type !== 'boolean'"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="field.required"
                                type="checkbox"
                                class="rounded border-neutral-300 dark:border-neutral-700"
                            />
                            Required
                        </label>
                        <span v-else />
                        <div class="flex gap-1">
                            <button
                                type="button"
                                class="cp-btn"
                                :disabled="i === 0"
                                aria-label="Move up"
                                @click="moveField(i, -1)"
                            >
                                &uarr;
                            </button>
                            <button
                                type="button"
                                class="cp-btn"
                                :disabled="i === form.fields.length - 1"
                                aria-label="Move down"
                                @click="moveField(i, 1)"
                            >
                                &darr;
                            </button>
                            <button
                                type="button"
                                class="cp-btn-danger"
                                @click="removeField(i)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </li>
            </ol>
        </section>

        <section class="cp-card p-6">
            <h2 class="font-semibold">Layout</h2>
            <p class="mb-4 text-sm text-neutral-500">
                HTML with
                <a
                    href="https://mustache.github.io/mustache.5.html"
                    target="_blank"
                    class="underline"
                    >Mustache</a
                >
                tags. The post's title and thumbnail are shown above it
                automatically. Leave it empty to generate one from the fields.
            </p>

            <div class="grid gap-4 lg:grid-cols-[1fr_16rem]">
                <div class="space-y-1.5">
                    <textarea
                        id="layout"
                        v-model="form.layout"
                        rows="20"
                        spellcheck="false"
                        aria-label="Layout"
                        class="cp-input font-mono text-xs leading-relaxed"
                    />
                    <p v-if="form.errors.layout" class="cp-error">
                        {{ form.errors.layout }}
                    </p>
                </div>
                <div class="text-sm">
                    <p class="mb-2 font-medium">Variables</p>
                    <ul class="space-y-2">
                        <li v-for="variable in variables" :key="variable.name">
                            <code
                                class="block text-xs break-all text-neutral-800 dark:text-neutral-200"
                            >
                                {{ variable.name }}
                            </code>
                            <span class="text-xs text-neutral-500">
                                {{ variable.note }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </form>
</template>
