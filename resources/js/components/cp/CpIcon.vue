<script setup lang="ts">
// Simple outline icons for the control panel's navigation, drawn on a 24px
// grid with the current text color. Decorative: the text beside them, or
// their button's label, says what they mean.
export type IconName =
    | 'dashboard'
    | 'health'
    | 'chevron'
    | 'plus'
    | 'posts'
    | 'templates'
    | 'tags'
    | 'links'
    | 'media'
    | 'users'
    | 'settings'
    | 'external'
    | 'logout'
    | 'monitor'
    | 'sun'
    | 'moon'
    | 'trash';

defineProps<{ name: IconName }>();
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <template v-if="name === 'dashboard'">
            <rect x="3.5" y="3.5" width="7" height="8" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="5" rx="1.5" />
            <rect x="13.5" y="11.5" width="7" height="9" rx="1.5" />
            <rect x="3.5" y="14.5" width="7" height="6" rx="1.5" />
        </template>
        <template v-else-if="name === 'health'">
            <path d="M3 12h4l2.5-6 5 12 2.5-6h4" />
        </template>
        <template v-else-if="name === 'chevron'">
            <path d="m6.5 9.5 5.5 5.5 5.5-5.5" />
        </template>
        <template v-else-if="name === 'plus'">
            <path d="M12 5v14M5 12h14" />
        </template>
        <template v-else-if="name === 'posts'">
            <path
                d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8z"
            />
            <path d="M14 3.5V8h4.5M9 12.5h6M9 16h4" />
        </template>
        <template v-else-if="name === 'templates'">
            <rect x="3.5" y="3.5" width="17" height="17" rx="2" />
            <path d="M3.5 9h17M9.5 9v11.5" />
        </template>
        <template v-else-if="name === 'tags'">
            <path
                d="M3.5 12.6V5a1.5 1.5 0 0 1 1.5-1.5h7.6l8 8a1.5 1.5 0 0 1 0 2.1l-6.9 6.9a1.5 1.5 0 0 1-2.1 0z"
            />
            <circle cx="8.5" cy="8.5" r="1.25" />
        </template>
        <template v-else-if="name === 'links'">
            <path d="M10 14a4 4 0 0 0 5.7 0l3.1-3.1a4 4 0 0 0-5.7-5.7l-1 1" />
            <path d="M14 10a4 4 0 0 0-5.7 0l-3.1 3.1a4 4 0 0 0 5.7 5.7l1-1" />
        </template>
        <template v-else-if="name === 'media'">
            <rect x="3.5" y="3.5" width="17" height="17" rx="2" />
            <circle cx="9" cy="9" r="1.75" />
            <path d="m20.5 15-4.5-4.5-10 10" />
        </template>
        <template v-else-if="name === 'users'">
            <circle cx="9.5" cy="8" r="3.5" />
            <path d="M3 20a6.5 6.5 0 0 1 13 0" />
            <path
                d="M15.5 4.6a3.5 3.5 0 0 1 0 6.8M21 20a6.5 6.5 0 0 0-3.5-5.8"
            />
        </template>
        <template v-else-if="name === 'settings'">
            <path d="M4 7h9M17 7h3M4 17h3M11 17h9" />
            <circle cx="15" cy="7" r="2" />
            <circle cx="9" cy="17" r="2" />
        </template>
        <template v-else-if="name === 'external'">
            <path d="M14 4.5h5.5V10M19.5 4.5 11 13" />
            <path
                d="M17 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 4 18.5v-10A1.5 1.5 0 0 1 5.5 7H10"
            />
        </template>
        <template v-else-if="name === 'monitor'">
            <rect x="3" y="4" width="18" height="12" rx="2" />
            <path d="M8 20h8M12 16v4" />
        </template>
        <template v-else-if="name === 'sun'">
            <circle cx="12" cy="12" r="4" />
            <path
                d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
            />
        </template>
        <template v-else-if="name === 'moon'">
            <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z" />
        </template>
        <template v-else-if="name === 'trash'">
            <path d="M4 7h16M10 11v6M14 11v6" />
            <path d="M5.5 7l1 12a2 2 0 0 0 2 1.8h7a2 2 0 0 0 2-1.8l1-12" />
            <path d="M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7" />
        </template>
        <template v-else-if="name === 'logout'">
            <path d="M9.5 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3.5" />
            <path d="m15 16.5 4.5-4.5L15 7.5M19.5 12H9.5" />
        </template>
    </svg>
</template>
