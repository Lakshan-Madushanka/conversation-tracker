import { afterEach, describe, expect, it, vi } from 'vite-plus/test';

describe('the appearance choice', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        vi.resetModules();
    });

    it('falls back to the system choice when storage is blocked', async () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new DOMException('denied', 'SecurityError');
        });
        vi.stubGlobal(
            'matchMedia',
            vi.fn(() => ({ matches: true })),
        );
        const { useAppearance } = await import('@/composables/useAppearance');

        expect(useAppearance().appearance.value).toBe('dark');
    });

    it('still switches appearance when the choice cannot be stored', async () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('denied', 'SecurityError');
        });

        const { useAppearance } = await import('@/composables/useAppearance');
        const { appearance, setAppearance } = useAppearance();

        expect(() => setAppearance('dark')).not.toThrow();
        expect(appearance.value).toBe('dark');
    });
});
