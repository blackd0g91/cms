<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ImageField from '@/components/cp/ImageField.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import SaveButton from '@/components/cp/SaveButton.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import CpLayout from '@/layouts/CpLayout.vue';
import { sentForm } from '@/lib/forms';
import { update } from '@/routes/cp/projects';
import type { Media } from '@/types';

defineOptions({ layout: CpLayout });

type SavedProject = {
    id: number;
    name: string;
    url: string;
    description: string | null;
    logo_id: number | null;
    background_id: number | null;
    logo: Media | null;
    background: Media | null;
};

const props = defineProps<{
    projects: SavedProject[];
}>();

// As long as the site shows it in full on the card.
const DESCRIPTION_MAX = 160;

type EditableProject = {
    // Stable across moves, for the list; id is null until saved.
    key: number;
    id: number | null;
    name: string;
    url: string;
    description: string;
    logo_id: number | null;
    background_id: number | null;
};

let nextKey = 0;

// The images as last chosen, to show in their fields when the list is
// built again after saving.
const images = new Map<number, Media>();

const editable = (projects: SavedProject[]): EditableProject[] =>
    projects.map((project) => {
        [project.logo, project.background].forEach(
            (media) => media && images.set(media.id, media),
        );

        return {
            key: nextKey++,
            id: project.id,
            name: project.name,
            url: project.url,
            description: project.description ?? '',
            logo_id: project.logo_id,
            background_id: project.background_id,
        };
    });

const form = useForm({
    projects: editable(props.projects),
});

const image = (id: number | null) => (id === null ? null : images.get(id));
const remember = (media: Media | null) => media && images.set(media.id, media);

const addProject = () => {
    form.projects.push({
        key: nextKey++,
        id: null,
        name: '',
        url: '',
        description: '',
        logo_id: null,
        background_id: null,
    });
};

const moveProject = (index: number, offset: number) => {
    const [project] = form.projects.splice(index, 1);
    form.projects.splice(index + offset, 0, project);
};

const removeProject = (index: number) => {
    form.projects.splice(index, 1);
};

const projectError = (index: number, key: string) =>
    form.errors[`projects.${index}.${key}` as keyof typeof form.errors];

const submit = () => {
    const sent = sentForm(form);

    form.transform((data) => ({
        projects: data.projects.map((project) => ({
            id: project.id,
            name: project.name,
            url: project.url,
            description: project.description,
            logo_id: project.logo_id,
            background_id: project.background_id,
        })),
    })).put(update().url, {
        preserveScroll: true,
        onSuccess: () => {
            // New projects now have ids, so saving again updates them.
            if (!sent.changed()) {
                form.projects = editable(props.projects);
                form.defaults();

                return;
            }

            // Changed while saving: keep the changes, only giving the new
            // projects their ids. Projects are saved in order.
            const ids = new Map(
                sent.data.projects.map((project, i) => [
                    project.key,
                    props.projects[i]?.id ?? project.id,
                ]),
            );
            const withIds = (projects: EditableProject[]) =>
                projects.map((project) => ({
                    ...project,
                    id: project.id ?? ids.get(project.key) ?? null,
                }));

            form.projects = withIds(form.projects);
            sent.saved({ ...sent.data, projects: withIds(sent.data.projects) });
        },
    });
};

useUnsavedChanges({
    isDirty: () => form.isDirty,
    save: () => !form.processing && submit(),
});
</script>

<template>
    <Head title="Projects" />
    <PageHeader title="Projects">
        <SaveButton
            form="projects-form"
            :processing="form.processing"
            :dirty="form.isDirty"
            :saved="form.recentlySuccessful"
        />
    </PageHeader>

    <p class="mb-6 text-sm text-neutral-500">
        Your apps and sites, shown as cards on the home page: the logo, name and
        description over the background image, faded.
    </p>

    <form
        id="projects-form"
        class="max-w-3xl space-y-6"
        @submit.prevent="submit"
    >
        <section class="cp-card p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold">Projects</h2>
                <button type="button" class="cp-btn" @click="addProject">
                    Add project
                </button>
            </div>

            <p
                v-if="form.projects.length === 0"
                class="text-sm text-neutral-500"
            >
                No projects yet.
            </p>

            <ol class="space-y-3">
                <li
                    v-for="(project, i) in form.projects"
                    :key="project.key"
                    class="rounded-md border border-neutral-200 p-4 dark:border-neutral-800"
                >
                    <fieldset class="space-y-4">
                        <legend class="sr-only">Project {{ i + 1 }}</legend>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label
                                    :for="`project-${project.key}-name`"
                                    class="cp-label"
                                >
                                    Name
                                </label>
                                <input
                                    :id="`project-${project.key}-name`"
                                    v-model="project.name"
                                    type="text"
                                    maxlength="60"
                                    class="cp-input"
                                />
                                <p
                                    v-if="projectError(i, 'name')"
                                    class="cp-error"
                                >
                                    {{ projectError(i, 'name') }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    :for="`project-${project.key}-url`"
                                    class="cp-label"
                                >
                                    Address
                                </label>
                                <input
                                    :id="`project-${project.key}-url`"
                                    v-model="project.url"
                                    type="text"
                                    inputmode="url"
                                    placeholder="app.example.com"
                                    class="cp-input"
                                />
                                <p
                                    v-if="projectError(i, 'url')"
                                    class="cp-error"
                                >
                                    {{ projectError(i, 'url') }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                :for="`project-${project.key}-description`"
                                class="cp-label"
                            >
                                Description
                            </label>
                            <textarea
                                :id="`project-${project.key}-description`"
                                v-model="project.description"
                                rows="2"
                                :maxlength="DESCRIPTION_MAX"
                                placeholder="What it is, in a line or two"
                                class="cp-input"
                            />
                            <p
                                v-if="projectError(i, 'description')"
                                class="cp-error"
                            >
                                {{ projectError(i, 'description') }}
                            </p>
                            <p v-else class="text-xs text-neutral-500">
                                {{ project.description.length }} of
                                {{ DESCRIPTION_MAX }} characters
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label
                                    :for="`project-${project.key}-logo`"
                                    class="cp-label"
                                >
                                    Logo
                                </label>
                                <ImageField
                                    :id="`project-${project.key}-logo`"
                                    v-model="project.logo_id"
                                    :initial="image(project.logo_id)"
                                    @change="remember"
                                />
                                <p class="text-xs text-neutral-500">
                                    Square works best. Without one, the card
                                    shows the name's first letter.
                                </p>
                                <p
                                    v-if="projectError(i, 'logo_id')"
                                    class="cp-error"
                                >
                                    {{ projectError(i, 'logo_id') }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    :for="`project-${project.key}-background`"
                                    class="cp-label"
                                >
                                    Background image
                                </label>
                                <ImageField
                                    :id="`project-${project.key}-background`"
                                    v-model="project.background_id"
                                    :initial="image(project.background_id)"
                                    @change="remember"
                                />
                                <p class="text-xs text-neutral-500">
                                    Like a screenshot. Shown faded behind the
                                    text.
                                </p>
                                <p
                                    v-if="projectError(i, 'background_id')"
                                    class="cp-error"
                                >
                                    {{ projectError(i, 'background_id') }}
                                </p>
                            </div>
                        </div>
                    </fieldset>

                    <div class="mt-3 flex justify-end gap-1">
                        <button
                            type="button"
                            class="cp-btn"
                            :disabled="i === 0"
                            aria-label="Move up"
                            @click="moveProject(i, -1)"
                        >
                            &uarr;
                        </button>
                        <button
                            type="button"
                            class="cp-btn"
                            :disabled="i === form.projects.length - 1"
                            aria-label="Move down"
                            @click="moveProject(i, 1)"
                        >
                            &darr;
                        </button>
                        <button
                            type="button"
                            class="cp-btn-danger"
                            @click="removeProject(i)"
                        >
                            Remove
                        </button>
                    </div>
                </li>
            </ol>
        </section>
    </form>
</template>
