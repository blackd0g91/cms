<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import CpIcon from '@/components/cp/CpIcon.vue';
import { create } from '@/routes/cp/templates/posts';

// A "New post" button that asks which template to write with, or goes
// straight to the only one.
const props = defineProps<{
    templates: { id: number; name: string; accent: string }[];
}>();

const open = ref(false);
const root = ref<HTMLElement>();

const closeOutside = (event: Event) => {
    if (!root.value?.contains(event.target as Node)) {
        open.value = false;
    }
};

const closeOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        open.value = false;
    }
};

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('pointerdown', closeOutside);
        document.addEventListener('keydown', closeOnEscape);
    } else {
        document.removeEventListener('pointerdown', closeOutside);
        document.removeEventListener('keydown', closeOnEscape);
    }
});

onBeforeUnmount(() => (open.value = false));

const single = () => props.templates[0];
</script>

<template>
    <Link
        v-if="templates.length === 1"
        :href="create(single().id)"
        class="cp-btn-primary"
    >
        <CpIcon name="plus" class="size-4" />
        New post
    </Link>

    <div v-else-if="templates.length > 1" ref="root" class="relative">
        <button
            type="button"
            class="cp-btn-primary"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="open = !open"
        >
            <CpIcon name="plus" class="size-4" />
            New post
            <CpIcon
                name="chevron"
                :class="['size-4 transition-transform', open && 'rotate-180']"
            />
        </button>

        <div
            v-if="open"
            role="menu"
            class="cp-card absolute right-0 z-30 mt-2 w-56 p-1 shadow-lg"
        >
            <Link
                v-for="template in templates"
                :key="template.id"
                :href="create(template.id)"
                role="menuitem"
                class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm hover:bg-neutral-100 focus-visible:bg-neutral-100 focus-visible:outline-none dark:hover:bg-neutral-800 dark:focus-visible:bg-neutral-800"
                @click="open = false"
            >
                <span
                    class="size-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: template.accent }"
                    aria-hidden="true"
                />
                <span class="truncate">{{ template.name }}</span>
            </Link>
        </div>
    </div>
</template>
