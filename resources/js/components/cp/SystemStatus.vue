<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

export type SystemReport = {
    deploy: {
        commit: string | null;
        committed_at: string | null;
        deployed_at: string | null;
    };
    php: string;
    laravel: string;
    environment: string;
    errors: { count: number; latest: { at: string; message: string } | null };
    disk: { free: number | null; total: number | null };
    database_size: number | null;
    uploads_size: number;
    warnings: { level: 'critical' | 'serious' | 'warning'; message: string }[];
};

const props = defineProps<{
    report: SystemReport;
}>();

// Status colors always come with an icon and words, never color alone.
const levels = {
    critical: {
        icon: '✕',
        label: 'Needs action',
        class: 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
    },
    serious: {
        icon: '!',
        label: 'Check this',
        class: 'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200',
    },
    warning: {
        icon: '▲',
        label: 'Heads up',
        class: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
    },
} as const;

const bytes = (value: number | null) => {
    if (value === null) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = value;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toFixed(size >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
};

const ago = (iso: string | null) => {
    if (!iso) {
        return null;
    }

    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

    if (minutes < 60) {
        return minutes <= 1 ? 'just now' : `${minutes} minutes ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 48) {
        return `${hours} hours ago`;
    }

    return new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
};

const usedShare = computed(() => {
    const { free, total } = props.report.disk;

    return free !== null && total ? 1 - free / total : null;
});
</script>

<template>
    <section id="system-status" class="cp-card">
        <div
            class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">System status</h2>
            <span
                v-if="report.warnings.length === 0"
                class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-900 dark:bg-green-950 dark:text-green-200"
            >
                ✓ All good
            </span>
            <span
                v-else
                class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-900 dark:bg-red-950 dark:text-red-200"
            >
                {{ report.warnings.length }}
                {{ report.warnings.length === 1 ? 'issue' : 'issues' }}
            </span>
        </div>

        <ul v-if="report.warnings.length" class="space-y-2 p-4 pb-0">
            <li
                v-for="(warning, i) in report.warnings"
                :key="i"
                :class="
                    cn(
                        'flex gap-3 rounded-md border px-3 py-2 text-sm',
                        levels[warning.level].class,
                    )
                "
            >
                <span class="font-bold" aria-hidden="true">{{
                    levels[warning.level].icon
                }}</span>
                <span>
                    <span class="font-medium"
                        >{{ levels[warning.level].label }}:</span
                    >
                    {{ warning.message }}
                </span>
            </li>
        </ul>

        <dl class="grid gap-x-8 gap-y-3 p-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs text-neutral-500">Deployed version</dt>
                <dd>
                    <code class="font-mono">{{
                        report.deploy.commit ?? 'unknown'
                    }}</code>
                    <span
                        v-if="report.deploy.deployed_at"
                        class="text-neutral-500"
                    >
                        &middot; deployed {{ ago(report.deploy.deployed_at) }}
                    </span>
                    <span v-else class="text-neutral-500">
                        &middot; not deployed with deploy.sh
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-neutral-500">Software</dt>
                <dd>
                    PHP {{ report.php }} &middot; Laravel {{ report.laravel }}
                    &middot;
                    <span class="text-neutral-500">{{
                        report.environment
                    }}</span>
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-neutral-500">
                    Errors in the last 24 hours
                </dt>
                <dd>
                    <template v-if="report.errors.count === 0"> None </template>
                    <template v-else>
                        {{ report.errors.count }} &middot; latest
                        {{ ago(report.errors.latest?.at ?? null) }}:
                        <code
                            class="mt-1 block rounded bg-neutral-100 px-2 py-1 font-mono text-xs break-all dark:bg-neutral-800"
                            >{{ report.errors.latest?.message }}</code
                        >
                        <span class="mt-1 block text-xs text-neutral-500">
                            Full details are in storage/logs/laravel.log on the
                            server.
                        </span>
                    </template>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-neutral-500">Disk space</dt>
                <dd>
                    {{ bytes(report.disk.free) }} free of
                    {{ bytes(report.disk.total) }}
                    <span
                        v-if="usedShare !== null"
                        class="mt-1 block h-1.5 w-full rounded-full bg-neutral-100 dark:bg-neutral-800"
                        role="img"
                        :aria-label="`${Math.round(usedShare * 100)}% of the disk used`"
                    >
                        <span
                            class="block h-full rounded-full bg-[#2a78d6] dark:bg-[#3987e5]"
                            :style="{ width: `${usedShare * 100}%` }"
                        />
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-neutral-500">Content size</dt>
                <dd>
                    Database {{ bytes(report.database_size) }} &middot; uploads
                    {{ bytes(report.uploads_size) }}
                </dd>
            </div>
        </dl>
    </section>
</template>
