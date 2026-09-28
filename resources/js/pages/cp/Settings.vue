<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ImageField from '@/components/cp/ImageField.vue';
import MarkdownEditor from '@/components/cp/MarkdownEditor.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import CpLayout from '@/layouts/CpLayout.vue';
import { update } from '@/routes/cp/settings';
import type { Media } from '@/types';

defineOptions({ layout: CpLayout });

const props = defineProps<{
    settings: {
        site_name: string;
        tagline: string | null;
        home_intro: string | null;
        footer_text: string | null;
        logo_id: number | null;
        favicon_id: number | null;
    };
    media: Record<number, Media>;
}>();

const form = useForm({
    site_name: props.settings.site_name,
    tagline: props.settings.tagline ?? '',
    home_intro: props.settings.home_intro ?? '',
    footer_text: props.settings.footer_text ?? '',
    logo_id: props.settings.logo_id,
    favicon_id: props.settings.favicon_id,
});

const submit = () => {
    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
};

useUnsavedChanges({
    isDirty: () => form.isDirty,
    save: () => !form.processing && submit(),
});
</script>

<template>
    <Head title="Settings" />
    <PageHeader title="Settings">
        <a href="/" target="_blank" class="cp-btn">View site</a>
    </PageHeader>

    <form class="max-w-3xl space-y-6" @submit.prevent="submit">
        <section class="cp-card space-y-5 p-6">
            <div class="space-y-1.5">
                <label for="site_name" class="cp-label">Site name</label>
                <input
                    id="site_name"
                    v-model="form.site_name"
                    type="text"
                    class="cp-input"
                />
                <p v-if="form.errors.site_name" class="cp-error">
                    {{ form.errors.site_name }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="tagline" class="cp-label">Tagline</label>
                <input
                    id="tagline"
                    v-model="form.tagline"
                    type="text"
                    placeholder="Recipes, cheatsheets and other notes"
                    class="cp-input"
                />
                <p class="text-xs text-neutral-500">
                    Shown under the site name in the header.
                </p>
                <p v-if="form.errors.tagline" class="cp-error">
                    {{ form.errors.tagline }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="home_intro" class="cp-label">Home page intro</label>
                <MarkdownEditor id="home_intro" v-model="form.home_intro" />
                <p class="text-xs text-neutral-500">
                    Markdown shown above the latest posts on the home page.
                </p>
                <p v-if="form.errors.home_intro" class="cp-error">
                    {{ form.errors.home_intro }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="footer_text" class="cp-label">Footer text</label>
                <input
                    id="footer_text"
                    v-model="form.footer_text"
                    type="text"
                    placeholder="© {year} {site_name}"
                    class="cp-input"
                />
                <p class="text-xs text-neutral-500">
                    <code>{year}</code> and <code>{site_name}</code> are
                    replaced. Leave empty for the default.
                </p>
                <p v-if="form.errors.footer_text" class="cp-error">
                    {{ form.errors.footer_text }}
                </p>
            </div>
        </section>

        <section class="cp-card space-y-5 p-6">
            <h2 class="font-semibold">Branding</h2>

            <div class="space-y-1.5">
                <label for="logo" class="cp-label">Logo</label>
                <ImageField
                    id="logo"
                    v-model="form.logo_id"
                    :initial="form.logo_id ? media[form.logo_id] : null"
                />
                <p class="text-xs text-neutral-500">
                    Shown beside the site name in the header, instead of the
                    first letter badge. It is shown 40px tall; a transparent
                    background works best.
                </p>
                <p v-if="form.errors.logo_id" class="cp-error">
                    {{ form.errors.logo_id }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="favicon" class="cp-label">Site icon</label>
                <ImageField
                    id="favicon"
                    v-model="form.favicon_id"
                    :initial="form.favicon_id ? media[form.favicon_id] : null"
                />
                <p class="text-xs text-neutral-500">
                    Used for browser tabs, bookmarks and home screen shortcuts.
                    Use a square PNG, ideally 180&times;180 or larger.
                </p>
                <p v-if="form.errors.favicon_id" class="cp-error">
                    {{ form.errors.favicon_id }}
                </p>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                class="cp-btn-primary"
                :disabled="form.processing"
            >
                Save
            </button>
            <span
                v-if="form.recentlySuccessful"
                class="text-sm text-neutral-500"
            >
                Saved.
            </span>
            <span
                v-else-if="form.isDirty"
                class="text-sm text-amber-700 dark:text-amber-400"
            >
                Unsaved changes &middot; Ctrl+S to save
            </span>
        </div>
    </form>
</template>
