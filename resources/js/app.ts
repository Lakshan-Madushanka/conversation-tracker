import { createInertiaApp } from '@inertiajs/vue3';
import { browserTimezone } from '@/lib/library-filters';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Keep the learner's zone in a cookie the server can read, so "today" on the
// review screens follows the learner's own local day everywhere.
const timezone = browserTimezone();

document.cookie = `app_timezone=${encodeURIComponent(timezone)}; path=/; max-age=31536000; samesite=lax`;

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
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
        color: '#4f46e5',
    },
});
