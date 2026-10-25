/**
 * jsdom leaves a few browser APIs out. Anything the app reads while a page is
 * being imported is stubbed here, so a page can be mounted in a test without
 * a real browser behind it.
 */

import { vi } from 'vite-plus/test';

/**
 * Inertia's Head needs the provider the real app starts. A page mounted on its
 * own has none, so the title tag is stubbed out; the title itself is checked in
 * the browser instead.
 */
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { name: 'Head', render: () => null },
}));

if (typeof window !== 'undefined' && window.matchMedia === undefined) {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: (query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addEventListener: () => {},
            removeEventListener: () => {},
            addListener: () => {},
            removeListener: () => {},
            dispatchEvent: () => false,
        }),
    });
}
