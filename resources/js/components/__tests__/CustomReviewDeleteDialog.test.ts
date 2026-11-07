import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import CustomReviewDeleteDialog from '@/components/CustomReviewDeleteDialog.vue';

/** Every delete the dialog sends, so the test can see how many went out. */
const deletes: string[] = [];

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    router: {
        delete: (url: string) => {
            deletes.push(url);
        },
    },
}));

let mounted: VueWrapper | undefined;

async function openDialog(attemptCount: number): Promise<VueWrapper> {
    mounted = mount(CustomReviewDeleteDialog, {
        attachTo: document.body,
        props: {
            open: true,
            name: 'Morning set',
            attemptCount,
            deleteUrl: '/custom-reviews/7',
        },
    });

    await flushPromises();

    return mounted;
}

function buttonWith(text: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll<HTMLButtonElement>('button')].find(
        (button) => button.textContent?.includes(text),
    );
}

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    deletes.length = 0;
    document.body.innerHTML = '';
});

describe('deleting a custom review', () => {
    it('names the review, says the items stay, and warns nothing comes back', async () => {
        await openDialog(3);

        expect(document.body.textContent).toContain('Delete Morning set?');
        expect(document.body.textContent).toContain(
            'Your items stay in their collections',
        );
        expect(document.body.textContent).toContain('This cannot be undone.');
        expect(document.body.textContent).toContain('Cancel');
    });

    it('states how many attempts leave with it', async () => {
        await openDialog(3);

        expect(document.body.textContent).toContain(
            'This will also delete its 3 attempts.',
        );
    });

    it('says nothing about attempts when there are none', async () => {
        await openDialog(0);

        expect(document.body.textContent).not.toContain('attempt');
    });

    it('sends one delete to the review’s own address', async () => {
        await openDialog(1);

        buttonWith('Delete review')?.click();
        await flushPromises();

        expect(deletes).toEqual(['/custom-reviews/7']);
    });
});
