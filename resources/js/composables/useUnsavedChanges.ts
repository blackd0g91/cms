import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

const MESSAGE = 'You have unsaved changes. Leave this page and lose them?';

/**
 * Warn before leaving an editor with unsaved changes, and save with
 * Ctrl+S / Cmd+S.
 *
 * Only page visits (GET) are guarded: saving, deleting and logging out are
 * deliberate actions and go through without a prompt.
 */
export function useUnsavedChanges(options: {
    isDirty: () => boolean;
    save: () => void;
}) {
    const onBeforeUnload = (event: BeforeUnloadEvent) => {
        if (options.isDirty()) {
            event.preventDefault();
        }
    };

    const onKeydown = (event: KeyboardEvent) => {
        if (
            (event.ctrlKey || event.metaKey) &&
            !event.altKey &&
            event.key.toLowerCase() === 's'
        ) {
            event.preventDefault();
            options.save();
        }
    };

    let removeBeforeListener: VoidFunction | undefined;

    onMounted(() => {
        window.addEventListener('beforeunload', onBeforeUnload);
        window.addEventListener('keydown', onKeydown);

        removeBeforeListener = router.on('before', (event) => {
            const { visit } = event.detail;

            if (
                visit.method === 'get' &&
                options.isDirty() &&
                !window.confirm(MESSAGE)
            ) {
                event.preventDefault();
            }
        });
    });

    onUnmounted(() => {
        window.removeEventListener('beforeunload', onBeforeUnload);
        window.removeEventListener('keydown', onKeydown);
        removeBeforeListener?.();
    });
}
