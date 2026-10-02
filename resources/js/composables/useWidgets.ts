import { onUnmounted, watch } from 'vue';
import type { Ref } from 'vue';
import { startWidgets } from '@/widgets';

/**
 * Makes the widgets in a markdown preview work, as they do on the site. They
 * are started again each time the preview's HTML changes, and stopped (a
 * ringing timer included) when it changes or the preview goes away.
 */
export function useWidgets(
    element: Ref<HTMLElement | undefined>,
    html: Ref<string>,
) {
    let stop: (() => void) | undefined;

    watch(
        [element, html],
        ([current]) => {
            stop?.();
            stop = current ? startWidgets(current) : undefined;
        },
        // After the new HTML is on the page.
        { flush: 'post' },
    );

    onUnmounted(() => stop?.());
}
