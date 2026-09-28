<script setup lang="ts">
import MarkdownEditor from '@/components/cp/MarkdownEditor.vue';
import type { Field, FieldValue } from '@/types';

const props = defineProps<{
    field: Field;
    id: string;
}>();

const model = defineModel<FieldValue>();

const listItems = () =>
    (Array.isArray(model.value) ? model.value : []) as string[];

const updateItem = (index: number, value: string) => {
    const items = [...listItems()];
    items[index] = value;
    model.value = items;
};

const addItem = () => {
    model.value = [...listItems(), ''];
};

const removeItem = (index: number) => {
    model.value = listItems().filter((_, i) => i !== index);
};

const inputValue = () =>
    Array.isArray(model.value) || typeof model.value === 'boolean'
        ? ''
        : (model.value ?? '');
</script>

<template>
    <textarea
        v-if="props.field.type === 'textarea'"
        :id="id"
        :value="inputValue()"
        rows="4"
        class="cp-input"
        @input="model = ($event.target as HTMLTextAreaElement).value"
    />

    <MarkdownEditor
        v-else-if="props.field.type === 'markdown'"
        :id="id"
        :model-value="typeof model === 'string' ? model : null"
        @update:model-value="model = $event ?? null"
    />

    <label
        v-else-if="props.field.type === 'boolean'"
        class="flex items-center gap-2 text-sm"
    >
        <input
            :id="id"
            type="checkbox"
            :checked="Boolean(model)"
            class="rounded border-neutral-300 dark:border-neutral-700"
            @change="model = ($event.target as HTMLInputElement).checked"
        />
        Yes
    </label>

    <select
        v-else-if="props.field.type === 'select'"
        :id="id"
        :value="inputValue()"
        class="cp-input"
        @change="model = ($event.target as HTMLSelectElement).value || null"
    >
        <option value="">&mdash;</option>
        <option
            v-for="option in props.field.options"
            :key="option"
            :value="option"
        >
            {{ option }}
        </option>
    </select>

    <div v-else-if="props.field.type === 'list'" class="space-y-2">
        <div
            v-for="(item, index) in listItems()"
            :key="index"
            class="flex gap-2"
        >
            <input
                :id="index === 0 ? id : undefined"
                type="text"
                :value="item"
                class="cp-input"
                @input="
                    updateItem(index, ($event.target as HTMLInputElement).value)
                "
                @keydown.enter.prevent="addItem"
            />
            <button type="button" class="cp-btn" @click="removeItem(index)">
                Remove
            </button>
        </div>
        <button type="button" class="cp-btn" @click="addItem">Add item</button>
    </div>

    <input
        v-else-if="props.field.type === 'number'"
        :id="id"
        type="number"
        step="any"
        :value="inputValue()"
        class="cp-input"
        @input="
            model =
                ($event.target as HTMLInputElement).value === ''
                    ? null
                    : Number(($event.target as HTMLInputElement).value)
        "
    />

    <input
        v-else
        :id="id"
        :type="props.field.type === 'date' ? 'date' : 'text'"
        :value="inputValue()"
        class="cp-input"
        @input="model = ($event.target as HTMLInputElement).value"
    />
</template>
