import type { InertiaForm } from '@inertiajs/vue3';

/** Whether two pieces of form data hold the same values. */
export const sameData = (a: unknown, b: unknown) =>
    JSON.stringify(a) === JSON.stringify(b);

/**
 * Remember what a form held when it was sent. Saving takes a moment, and
 * anything typed meanwhile was not saved, so once the save succeeds only
 * what was sent may be marked as saved: later edits stay unsaved changes
 * (and keep their local backup and the warning before leaving).
 */
export function sentForm<T extends object>(form: InertiaForm<T>) {
    const data = JSON.parse(JSON.stringify(form.data())) as T;

    return {
        /** A copy of the data as it was sent. */
        data,
        /** Whether the form was changed after it was sent. */
        changed: () => !sameData(form.data(), data),
        /**
         * Mark the data as saved, as sent or as the server saved it. Edits
         * made since stay unsaved changes.
         */
        saved: (saved: T = data) => {
            form.defaults(saved);
            form.isDirty = !sameData(form.data(), saved);
        },
    };
}
