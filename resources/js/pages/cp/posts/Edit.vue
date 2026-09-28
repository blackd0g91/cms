<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FieldInput from '@/components/cp/FieldInput.vue';
import ImageField from '@/components/cp/ImageField.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import CpLayout from '@/layouts/CpLayout.vue';
import { slugify } from '@/lib/utils';
import { index } from '@/routes/cp/posts';
import { destroy, duplicate, store, update } from '@/routes/cp/templates/posts';
import type {
    Field,
    FieldValue,
    Media,
    Post,
    PostStatus,
    Template,
} from '@/types';

defineOptions({ layout: CpLayout });

const props = defineProps<{
    template: Pick<Template, 'id' | 'name' | 'handle' | 'fields'>;
    post: Omit<Post, 'updated_at'> | null;
    media: Record<number, Media>;
}>();

const emptyValue = (field: Field): FieldValue => {
    switch (field.type) {
        case 'boolean':
            return false;
        case 'list':
            return [''];
        default:
            return null;
    }
};

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    status: (props.post?.status ?? 'draft') as PostStatus,
    thumbnail_id: props.post?.thumbnail_id ?? null,
    // "data" is reserved by useForm, so it is renamed when submitting.
    values: Object.fromEntries(
        props.template.fields.map((field) => [
            field.handle,
            props.post?.data[field.handle] ?? emptyValue(field),
        ]),
    ) as Record<string, FieldValue>,
});

const slugTouched = ref(props.post !== null);

const onTitleInput = () => {
    if (!slugTouched.value) {
        form.slug = slugify(form.title);
    }
};

const dataError = (handle: string) => {
    const key = `data.${handle}`;

    return Object.entries(form.errors).find(
        ([name]) => name === key || name.startsWith(`${key}.`),
    )?.[1];
};

const submit = () => {
    form.transform(({ values, ...rest }) => ({ ...rest, data: values })).submit(
        props.post
            ? update([props.template.id, props.post.id])
            : store(props.template.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                // The page stays mounted after creating, so the slug is now fixed.
                slugTouched.value = true;
                form.defaults();
            },
        },
    );
};

useUnsavedChanges({
    isDirty: () => form.isDirty,
    save: () => !form.processing && submit(),
});

const deletePost = () => {
    if (!props.post || !confirm(`Delete "${props.post.title}"?`)) {
        return;
    }

    router.delete(destroy([props.template.id, props.post.id]).url);
};
</script>

<template>
    <Head :title="post ? post.title : `New ${template.name} post`" />
    <PageHeader
        :title="post ? post.title : 'New post'"
        :back="{ label: 'Posts', href: index().url }"
    >
        <template v-if="post">
            <Link
                :href="duplicate([template.id, post.id])"
                method="post"
                as="button"
                :preserve-state="false"
                class="cp-btn"
                :disabled="form.isDirty"
                :title="
                    form.isDirty
                        ? 'Save your changes before duplicating'
                        : 'Create a draft copy of this post'
                "
            >
                Duplicate
            </Link>
            <a :href="post.url" target="_blank" class="cp-btn">View</a>
        </template>
    </PageHeader>

    <form
        class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_16rem]"
        @submit.prevent="submit"
    >
        <section class="cp-card space-y-5 p-6">
            <div class="space-y-1.5">
                <label for="title" class="cp-label">Title</label>
                <input
                    id="title"
                    v-model="form.title"
                    v-focus="!post"
                    type="text"
                    class="cp-input text-base"
                    @input="onTitleInput"
                />
                <p v-if="form.errors.title" class="cp-error">
                    {{ form.errors.title }}
                </p>
            </div>

            <div
                v-for="field in template.fields"
                :key="field.handle"
                class="space-y-1.5"
            >
                <label :for="`data-${field.handle}`" class="cp-label">
                    {{ field.label }}
                    <span
                        v-if="field.required && field.type !== 'boolean'"
                        class="text-red-500"
                        >*</span
                    >
                </label>
                <FieldInput
                    :id="`data-${field.handle}`"
                    v-model="form.values[field.handle]"
                    :field="field"
                    :media="
                        field.type === 'image'
                            ? media[form.values[field.handle] as number]
                            : null
                    "
                />
                <p v-if="dataError(field.handle)" class="cp-error">
                    {{ dataError(field.handle) }}
                </p>
            </div>
        </section>

        <aside class="space-y-4">
            <section class="cp-card space-y-2 p-4">
                <label for="thumbnail" class="cp-label">Thumbnail</label>
                <ImageField
                    id="thumbnail"
                    v-model="form.thumbnail_id"
                    :initial="
                        form.thumbnail_id ? media[form.thumbnail_id] : null
                    "
                />
                <p v-if="form.errors.thumbnail_id" class="cp-error">
                    {{ form.errors.thumbnail_id }}
                </p>
            </section>

            <section class="cp-card space-y-4 p-4">
                <div class="space-y-1.5">
                    <label for="status" class="cp-label">Status</label>
                    <select id="status" v-model="form.status" class="cp-input">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="slug" class="cp-label">Slug</label>
                    <input
                        id="slug"
                        v-model="form.slug"
                        type="text"
                        class="cp-input font-mono"
                        @input="slugTouched = true"
                    />
                    <p class="text-xs break-all text-neutral-500">
                        /{{ template.handle }}/{{ form.slug }}
                    </p>
                    <p v-if="form.errors.slug" class="cp-error">
                        {{ form.errors.slug }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        class="cp-btn-primary w-full"
                        :disabled="form.processing"
                    >
                        {{ post ? 'Save' : 'Create post' }}
                    </button>
                </div>
                <p
                    v-if="form.recentlySuccessful"
                    class="text-center text-sm text-neutral-500"
                >
                    Saved.
                </p>
                <p
                    v-else-if="form.isDirty"
                    class="text-center text-sm text-amber-700 dark:text-amber-400"
                >
                    Unsaved changes &middot; Ctrl+S to save
                </p>
            </section>

            <button
                v-if="post"
                type="button"
                class="cp-btn-danger w-full"
                @click="deletePost"
            >
                Delete post
            </button>
        </aside>
    </form>
</template>
