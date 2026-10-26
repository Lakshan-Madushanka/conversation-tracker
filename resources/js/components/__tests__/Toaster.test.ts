import { describe, expect, it } from 'vite-plus/test';
// The toaster's source is read as text on purpose: the stylesheet it imports is
// not applied in jsdom, so only the source can prove the toaster still asks for
// it. See the comment in the component for what goes wrong without it.
import toasterSource from '@/components/ui/sonner/Sonner.vue?raw';

describe('the toaster', () => {
    it('asks for the styles that position and shape every toast', () => {
        expect(toasterSource).toContain("import 'vue-sonner/style.css'");
    });
});
