/**
 * Start one kind of widget on every element matching `selector` in `root`.
 * `start` gets a signal for its event listeners and may return what else
 * stops it (timers, sounds). Returns a function that stops them all.
 *
 * Elements already started are skipped, so starting twice is harmless.
 */
export function eachWidget<T extends HTMLElement>(
    root: ParentNode,
    selector: string,
    start: (element: T, signal: AbortSignal) => (() => void) | void,
): () => void {
    const listeners = new AbortController();
    const stops: (() => void)[] = [];

    root.querySelectorAll<T>(selector).forEach((element) => {
        if (element.dataset.widgetStarted !== undefined) {
            return;
        }

        element.dataset.widgetStarted = '';
        const stop = start(element, listeners.signal);

        stops.push(() => {
            stop?.();
            delete element.dataset.widgetStarted;
        });
    });

    return () => {
        listeners.abort();
        stops.forEach((stop) => stop());
    };
}
