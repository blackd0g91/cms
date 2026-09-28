import { createInertiaApp } from '@inertiajs/vue3';

void createInertiaApp({
    title: (title, page) => {
        const siteName = String(page.props.name);

        return title ? `${title} - ${siteName}` : siteName;
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});
