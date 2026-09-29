import { onMounted, onUnmounted, ref, watch } from 'vue';

export type LocalDraft<T> = {
    data: T;
    savedAt: string;
    // What the server version was when the draft started, to detect that it
    // has been saved again since (for example from another device).
    version: string | null;
};

const PREFIX = 'cp.draft.';

const read = <T>(key: string): LocalDraft<T> | null => {
    try {
        const raw = localStorage.getItem(PREFIX + key);

        return raw ? (JSON.parse(raw) as LocalDraft<T>) : null;
    } catch {
        return null;
    }
};

const write = (key: string, draft: LocalDraft<unknown> | null) => {
    try {
        if (draft) {
            localStorage.setItem(PREFIX + key, JSON.stringify(draft));
        } else {
            localStorage.removeItem(PREFIX + key);
        }
    } catch {
        // Storage can be full or unavailable (private windows). Autosave is a
        // safety net, so failing quietly is fine.
    }
};

/**
 * Keep a copy of unsaved form data in this browser, so a closed tab or a
 * crash does not lose it. A draft found on load is offered, never applied
 * automatically; see `found`.
 */
export function useLocalDraft<T>(options: {
    key: () => string;
    data: () => T;
    isDirty: () => boolean;
    version: () => string | null;
}) {
    const found = ref<LocalDraft<T> | null>(null);
    let timer: ReturnType<typeof setTimeout> | undefined;

    const save = () => {
        write(
            options.key(),
            options.isDirty()
                ? {
                      data: options.data(),
                      savedAt: new Date().toISOString(),
                      version: options.version(),
                  }
                : null,
        );
    };

    onMounted(() => {
        found.value = read<T>(options.key());

        watch(
            () => [options.data(), options.isDirty()],
            () => {
                clearTimeout(timer);
                timer = setTimeout(save, 800);
            },
            { deep: true },
        );
    });

    onUnmounted(() => {
        // Write what is pending right away, rather than losing the last second.
        if (timer) {
            clearTimeout(timer);
            save();
        }
    });

    return {
        found,
        /** Forget the stored draft (after restoring or discarding it). */
        dismiss: () => (found.value = null),
        discard: () => {
            found.value = null;
            write(options.key(), null);
        },
        /** Remove the stored draft, for example once the form is saved. */
        clear: (key = options.key()) => write(key, null),
    };
}
