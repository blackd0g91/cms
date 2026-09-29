<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ImageField from '@/components/cp/ImageField.vue';
import MarkdownEditor from '@/components/cp/MarkdownEditor.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import CpLayout from '@/layouts/CpLayout.vue';
import { update } from '@/routes/cp/settings';
import type { Media } from '@/types';

type LogoBackground = {
    type: 'none' | 'solid' | 'gradient';
    from: string;
    to: string;
    angle: number;
};

defineOptions({ layout: CpLayout });

const props = defineProps<{
    settings: {
        site_name: string;
        tagline: string | null;
        home_intro: string | null;
        footer_text: string | null;
        logo_id: number | null;
        logo_background: LogoBackground | null;
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
    logo_background: {
        type: 'none',
        from: '#221d17',
        to: '#c2410c',
        angle: 135,
        ...props.settings.logo_background,
    } as LogoBackground,
    favicon_id: props.settings.favicon_id,
});

const backgroundCss = computed(() => {
    const { type, from, to, angle } = form.logo_background;

    if (type === 'solid') {
        return { background: from };
    }

    if (type === 'gradient') {
        return { background: `linear-gradient(${angle}deg, ${from}, ${to})` };
    }

    return undefined;
});

const logo = ref<Media | null>(
    props.settings.logo_id
        ? (props.media[props.settings.logo_id] ?? null)
        : null,
);
const logoUrl = computed(() => logo.value?.url ?? null);

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
                    @change="logo = $event"
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

            <fieldset class="space-y-3">
                <legend class="cp-label">Logo background</legend>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label
                        v-for="option in [
                            { value: 'none', label: 'None' },
                            { value: 'solid', label: 'Solid color' },
                            { value: 'gradient', label: 'Gradient' },
                        ] as const"
                        :key="option.value"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="form.logo_background.type"
                            type="radio"
                            :value="option.value"
                        />
                        {{ option.label }}
                    </label>
                </div>

                <div
                    v-if="form.logo_background.type !== 'none'"
                    class="flex flex-wrap items-center gap-4"
                >
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="form.logo_background.from"
                            type="color"
                            class="h-9 w-12 cursor-pointer rounded-md border border-neutral-300 bg-white p-1 dark:border-neutral-700 dark:bg-neutral-950"
                        />
                        {{
                            form.logo_background.type === 'gradient'
                                ? 'From'
                                : 'Color'
                        }}
                    </label>
                    <template v-if="form.logo_background.type === 'gradient'">
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="form.logo_background.to"
                                type="color"
                                class="h-9 w-12 cursor-pointer rounded-md border border-neutral-300 bg-white p-1 dark:border-neutral-700 dark:bg-neutral-950"
                            />
                            To
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            Angle
                            <input
                                v-model.number="form.logo_background.angle"
                                type="range"
                                min="0"
                                max="360"
                                step="15"
                            />
                            <span class="w-10 font-mono text-xs"
                                >{{ form.logo_background.angle }}&deg;</span
                            >
                        </label>
                    </template>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs text-neutral-500">Preview</span>
                    <span
                        class="flex items-center gap-3 rounded-lg bg-[#f5efe3] px-4 py-3 text-[#221d17]"
                    >
                        <span
                            v-if="logoUrl && backgroundCss"
                            class="grid h-10 min-w-10 place-items-center rounded-xl p-1.5 shadow-sm"
                            :style="backgroundCss"
                        >
                            <img
                                :src="logoUrl"
                                alt=""
                                class="h-7 w-auto max-w-36 object-contain"
                            />
                        </span>
                        <img
                            v-else-if="logoUrl"
                            :src="logoUrl"
                            alt=""
                            class="h-10 w-auto max-w-40 object-contain"
                        />
                        <span
                            v-else
                            class="grid size-10 place-items-center rounded-full font-serif text-xl font-semibold"
                            :class="
                                backgroundCss
                                    ? 'text-white shadow-sm'
                                    : 'bg-[#221d17] text-[#f5efe3]'
                            "
                            :style="backgroundCss"
                        >
                            {{ form.site_name.charAt(0).toUpperCase() }}
                        </span>
                        <span class="font-serif text-xl font-semibold">
                            {{ form.site_name }}
                        </span>
                    </span>
                </div>
                <p class="text-xs text-neutral-500">
                    Useful for SVG or transparent logos that need something
                    behind them. Without a logo, it colors the letter badge.
                </p>
                <p
                    v-for="key in [
                        'logo_background.type',
                        'logo_background.from',
                        'logo_background.to',
                        'logo_background.angle',
                    ] as const"
                    v-show="form.errors[key]"
                    :key="key"
                    class="cp-error"
                >
                    {{ form.errors[key] }}
                </p>
            </fieldset>

            <div class="space-y-1.5">
                <label for="favicon" class="cp-label">Site icon</label>
                <ImageField
                    id="favicon"
                    v-model="form.favicon_id"
                    :initial="form.favicon_id ? media[form.favicon_id] : null"
                />
                <p class="text-xs text-neutral-500">
                    Used for browser tabs, bookmarks and home screen shortcuts.
                    Use a square SVG, or a PNG of 180&times;180 or larger.
                    iPhones and iPads can't use SVG for home screen icons, so
                    they get the default one.
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
