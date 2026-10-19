/**
 * jsdom leaves a few browser APIs out. Anything the app reads while a page is
 * being imported is stubbed here, so a page can be mounted in a test without
 * a real browser behind it.
 */

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
