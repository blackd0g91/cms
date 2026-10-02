<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { index } from '@/routes/cp/posts';
import { create as createTemplate } from '@/routes/cp/templates';
import { create } from '@/routes/cp/templates/posts';
import type { Template } from '@/types';

defineOptions({ layout: CpLayout });

defineProps<{
    templates: Pick<Template, 'id' | 'name' | 'handle' | 'description'>[];
}>();
</script>

<template>
    <Head title="New post" />
    <PageHeader
        title="New post"
        :crumbs="[{ label: 'Posts', href: index().url }]"
    />

    <div
        v-if="templates.length === 0"
        class="cp-card p-6 text-sm text-neutral-500"
    >
        Posts are written with a template, and there are none yet.
        <Link
            v-if="$page.props.auth.user.role === 'admin'"
            :href="createTemplate()"
            class="ml-1 underline"
        >
            Create a template
        </Link>
        <template v-else>Ask an admin to create one.</template>
    </div>

    <template v-else>
        <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
            Which template should this post use?
        </p>
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="template in templates" :key="template.id">
                <Link
                    :href="create(template.id)"
                    class="cp-card block h-full p-4 hover:border-neutral-400 dark:hover:border-neutral-600"
                >
                    <p class="font-medium">{{ template.name }}</p>
                    <p class="text-xs text-neutral-500">
                        /{{ template.handle }}
                    </p>
                    <p
                        v-if="template.description"
                        class="mt-2 text-sm text-neutral-600 dark:text-neutral-400"
                    >
                        {{ template.description }}
                    </p>
                </Link>
            </li>
        </ul>
    </template>
</template>
