<script setup lang="ts">
withDefaults(
    defineProps<{
        // The id of the form it submits: it sits outside it, in the page header.
        form: string;
        processing: boolean;
        dirty: boolean;
        // Just saved (useForm's recentlySuccessful).
        saved: boolean;
        label?: string;
    }>(),
    { label: 'Save' },
);
</script>

<template>
    <!-- The status before the button. Its space is kept when there is none,
         so the header's buttons don't move while typing. Changes are read
         out: "Unsaved changes", then "Saved". -->
    <span class="grid size-8 place-items-center" aria-live="polite">
        <span
            v-if="saved"
            class="group relative grid size-8 place-items-center rounded-full text-green-600 outline-none focus-visible:ring-2 focus-visible:ring-neutral-300 dark:text-green-400 dark:focus-visible:ring-neutral-700"
            tabindex="0"
        >
            <svg
                class="size-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M5 12l5 5l9 -10" />
            </svg>
            <span class="sr-only">Saved</span>
            <span
                class="pointer-events-none absolute top-full right-0 z-30 mt-2 hidden rounded-md bg-neutral-900 px-2 py-1 text-xs whitespace-nowrap text-white shadow dark:bg-neutral-100 dark:text-neutral-900 [.group:focus_&]:block [.group:hover_&]:block"
                aria-hidden="true"
            >
                Saved
            </span>
        </span>
        <span
            v-else-if="dirty"
            class="group relative grid size-8 place-items-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-neutral-300 dark:focus-visible:ring-neutral-700"
            tabindex="0"
        >
            <span
                class="size-2.5 rounded-full bg-amber-500 dark:bg-amber-400"
                aria-hidden="true"
            />
            <span class="sr-only">Unsaved changes. Press Ctrl+S to save.</span>
            <!-- Plain :hover rules (not Tailwind's group-hover, which needs a
                 mouse), so tapping it shows the message on phones too. -->
            <span
                class="pointer-events-none absolute top-full right-0 z-30 mt-2 hidden rounded-md bg-neutral-900 px-2 py-1 text-xs whitespace-nowrap text-white shadow dark:bg-neutral-100 dark:text-neutral-900 [.group:focus_&]:block [.group:hover_&]:block"
                aria-hidden="true"
            >
                Unsaved changes &middot; Ctrl+S to save
            </span>
        </span>
    </span>

    <button
        type="submit"
        :form="form"
        class="cp-btn-primary"
        :disabled="processing"
    >
        {{ label }}
    </button>
</template>
