<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref } from 'vue';
import { cn } from '@/lib/utils';

type EmojiData = {
    groups: { id: number; name: string }[];
    emoji: { e: string; n: string; g: number; k: string[]; s: string[] }[];
};

const emit = defineEmits<{
    select: [emoji: string];
}>();

const RECENT_KEY = 'cp.emoji.recent';
const RECENT_LIMIT = 24;

const open = ref(false);
const data = ref<EmojiData | null>(null);
const query = ref('');
const group = ref<number | 'recent'>('recent');
const recent = ref<string[]>([]);
const root = ref<HTMLElement>();
const search = ref<HTMLInputElement>();

const readRecent = () => {
    try {
        const saved = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');

        return Array.isArray(saved)
            ? saved.filter((e) => typeof e === 'string')
            : [];
    } catch {
        return [];
    }
};

const onOutsideClick = (event: MouseEvent) => {
    if (root.value && !root.value.contains(event.target as Node)) {
        close();
    }
};

const show = async () => {
    open.value = true;
    recent.value = readRecent();
    group.value = recent.value.length ? 'recent' : 0;
    document.addEventListener('mousedown', onOutsideClick);

    // The list is large, so it is only loaded the first time.
    data.value ??= (await import('@/data/emoji.json')).default as EmojiData;

    await nextTick();
    search.value?.focus();
};

const close = () => {
    open.value = false;
    query.value = '';
    document.removeEventListener('mousedown', onOutsideClick);
};

onUnmounted(() => document.removeEventListener('mousedown', onOutsideClick));

const choose = (emoji: string) => {
    emit('select', emoji);
    recent.value = [emoji, ...recent.value.filter((e) => e !== emoji)].slice(
        0,
        RECENT_LIMIT,
    );

    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(recent.value));
    } catch {
        // Not remembering recent emoji is fine.
    }

    close();
};

const results = computed(() => {
    const all = data.value?.emoji ?? [];
    const words = query.value.toLowerCase().trim().split(/\s+/).filter(Boolean);

    if (words.length) {
        const text = words.join(' ');

        // Best matches first: exact name or shortcode, then names starting
        // with the search, then anywhere in the name, then keywords only.
        const rank = (item: EmojiData['emoji'][number]) => {
            if (item.n === text || item.s.includes(text.replace(/\s+/g, '_'))) {
                return 0;
            }

            if (item.n.startsWith(text)) {
                return 1;
            }

            return words.every((word) => item.n.includes(word)) ? 2 : 3;
        };

        return all
            .filter((item) =>
                words.every(
                    (word) =>
                        item.n.includes(word) ||
                        item.k.some((tag) => tag.startsWith(word)) ||
                        item.s.some((code) => code.includes(word)),
                ),
            )
            .map((item, index) => ({ item, index, rank: rank(item) }))
            .sort((a, b) => a.rank - b.rank || a.index - b.index)
            .map(({ item }) => item);
    }

    if (group.value === 'recent') {
        return recent.value.map(
            (e) =>
                all.find((item) => item.e === e) ?? {
                    e,
                    n: e,
                    g: -1,
                    k: [],
                    s: [],
                },
        );
    }

    return all.filter((item) => item.g === group.value);
});

// The first emoji of each group is its tab icon.
const tabs = computed(() =>
    (data.value?.groups ?? []).map((g) => ({
        ...g,
        icon: data.value?.emoji.find((item) => item.g === g.id)?.e ?? '?',
    })),
);

const label = (item: EmojiData['emoji'][number]) =>
    item.s.length ? `${item.n} (:${item.s[0]}:)` : item.n;

defineExpose({ show });
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            title="Emoji"
            aria-label="Insert emoji"
            :aria-expanded="open"
            class="rounded px-2 py-1 text-xs text-neutral-600 hover:bg-white hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
            @mousedown.prevent
            @click="open ? close() : show()"
        >
            😊
        </button>

        <div
            v-if="open"
            class="absolute top-full left-0 z-30 mt-1 w-80 rounded-lg border border-neutral-200 bg-white shadow-xl dark:border-neutral-800 dark:bg-neutral-900"
            @keydown.esc.stop="close"
        >
            <div
                class="border-b border-neutral-200 p-2 dark:border-neutral-800"
            >
                <input
                    ref="search"
                    v-model="query"
                    type="search"
                    placeholder="Search emoji…"
                    aria-label="Search emoji"
                    class="cp-input py-1.5 text-sm"
                />
            </div>

            <div
                v-if="!query"
                class="flex gap-0.5 border-b border-neutral-200 px-1 py-1 dark:border-neutral-800"
                role="tablist"
            >
                <button
                    v-if="recent.length"
                    type="button"
                    role="tab"
                    title="Recently used"
                    :aria-selected="group === 'recent'"
                    :class="
                        cn(
                            'flex-1 rounded py-1 text-sm',
                            group === 'recent' &&
                                'bg-neutral-100 dark:bg-neutral-800',
                        )
                    "
                    @click="group = 'recent'"
                >
                    🕘
                </button>
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    :title="tab.name"
                    :aria-selected="group === tab.id"
                    :class="
                        cn(
                            'flex-1 rounded py-1 text-sm',
                            group === tab.id &&
                                'bg-neutral-100 dark:bg-neutral-800',
                        )
                    "
                    @click="group = tab.id"
                >
                    {{ tab.icon }}
                </button>
            </div>

            <div class="h-64 overflow-y-auto p-1.5">
                <p v-if="!data" class="p-2 text-sm text-neutral-500">
                    Loading…
                </p>
                <p
                    v-else-if="results.length === 0"
                    class="p-2 text-sm text-neutral-500"
                >
                    No emoji found.
                </p>
                <div v-else class="grid grid-cols-8 gap-0.5">
                    <button
                        v-for="item in results"
                        :key="item.e"
                        type="button"
                        :title="label(item)"
                        :aria-label="item.n"
                        class="grid aspect-square place-items-center rounded text-xl hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        @click="choose(item.e)"
                    >
                        {{ item.e }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
