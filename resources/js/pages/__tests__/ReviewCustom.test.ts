import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import CustomReviewControls from '@/components/review/CustomReviewControls.vue';
import ReviewCustom from '@/pages/review/Custom.vue';
import { custom as customUrl, customStore } from '@/routes/review';
import type {
    CollectionTopicOption,
    CustomReviewChoices,
    CustomReviewCollectionOption,
    CustomReviewPreview,
} from '@/types';

/** Longer than the page’s own wait, so a scheduled count has gone out. */
const REFRESH_WAIT_MS = 500;

const collections: CustomReviewCollectionOption[] = [
    { value: 'words', label: 'Words' },
    { value: 'phrases', label: 'Phrases' },
    { value: 'grammar_notes', label: 'Grammar notes' },
    { value: 'mistakes', label: 'Mistakes' },
    { value: 'questions', label: 'Past questions' },
];

const topics: CollectionTopicOption[] = [
    { id: 2, name: 'Cooking', count: 4 },
    { id: 5, name: 'Travel', count: 2 },
];

function preview(
    overrides: Partial<CustomReviewPreview> = {},
): CustomReviewPreview {
    return {
        count: 12,
        size: 8,
        minutes: 6,
        mix: '5 flashcards, 3 mistakes to fix, 1 blank to fill in',
        comfortable: true,
        kinds: 3,
        ...overrides,
    };
}

function choices(
    overrides: Partial<CustomReviewChoices> = {},
): CustomReviewChoices {
    return {
        topics: [],
        collections: [],
        starred: false,
        learned: false,
        size: 8,
        ...overrides,
    };
}

let mounted: VueWrapper | undefined;

function mountPage(overrides: Partial<CustomReviewPreview> = {}) {
    mounted = mount(ReviewCustom, {
        attachTo: document.body,
        props: {
            choices: choices(),
            topics,
            collections,
            sizes: [5, 8, 10, 15, 20],
            dailySize: 8,
            preview: preview(overrides),
        },
    });

    return mounted;
}

function button(label: string) {
    return mounted
        ?.findAll('button')
        .find((item) => item.text().trim() === label);
}

function desktopControls() {
    return mounted?.findAllComponents(CustomReviewControls)[0];
}

/** Let the page’s wait elapse so the count request goes out. */
async function waitForCountRequest(): Promise<void> {
    await new Promise((resolve) => {
        setTimeout(resolve, REFRESH_WAIT_MS);
    });
    await flushPromises();
}

beforeEach(() => {
    vi.spyOn(router, 'get').mockImplementation(() => {});
    vi.spyOn(router, 'post').mockImplementation(() => {});
});

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('what the choices match', () => {
    it('reads the count, the estimate and the mix the server sent', () => {
        mountPage();

        expect(mounted?.text()).toContain('12 items match');
        expect(mounted?.text()).toContain('About 6 minutes · 12 items match');
        expect(mounted?.text()).toContain('5 flashcards, 3 mistakes to fix');
    });

    it('agrees with itself on a single match', () => {
        mountPage({ count: 1, minutes: 1, comfortable: false });

        expect(mounted?.text()).toContain('1 item matches');
        expect(mounted?.text()).toContain('About 1 minute · 1 item matches');
    });

    it('asks for nothing when the page opens, because the server already counted', () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        expect(get).not.toHaveBeenCalled();
    });
});

describe('too few to fill the sitting', () => {
    it('says so kindly and lets the learner start anyway', () => {
        mountPage({ count: 2, comfortable: false, kinds: 2 });

        expect(mounted?.text()).toContain('Only 2 items match.');
        expect(mounted?.text()).toContain(
            'Try adding a topic or including Learned items.',
        );
        expect(mounted?.text()).toContain(
            'A short review is fine — start with these 2 items.',
        );
        expect(
            button('Start custom review')?.attributes('disabled'),
        ).toBeUndefined();
    });

    it('uses the singular for one item', () => {
        mountPage({ count: 1, comfortable: false });

        expect(mounted?.text()).toContain('Only 1 item matches.');
        expect(mounted?.text()).toContain('start with these 1 item');
    });
});

describe('nothing to review', () => {
    it('explains the choices rather than blaming the learner', () => {
        mountPage({ count: 0, comfortable: false, mix: '' });

        expect(mounted?.text()).toContain('Nothing matches these choices yet');
        expect(mounted?.text()).toContain(
            'Archived and unfinished sessions never appear',
        );
        expect(mounted?.text()).toContain(
            'Start becomes available as soon as something matches.',
        );
    });

    it('keeps Start out of reach until something matches', () => {
        mountPage({ count: 0, comfortable: false });

        expect(button('Start custom review')?.attributes('disabled')).toBe('');
    });

    it('never starts a sitting with nothing in it', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        mountPage({ count: 0, comfortable: false });

        await button('Start custom review')?.trigger('click');

        expect(post).not.toHaveBeenCalled();
    });
});

describe('a choice the learner changes', () => {
    it('asks the server with the new choices in the address', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        desktopControls()?.vm.$emit('change', choices({ topics: [2] }));
        await waitForCountRequest();

        expect(get).toHaveBeenCalledWith(
            customUrl.url({ query: { topics: '2' } }),
            {},
            expect.objectContaining({
                only: ['preview', 'topics'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }),
        );
    });

    it('says the count is being worked out while it waits', async () => {
        mountPage();

        desktopControls()?.vm.$emit('change', choices({ starred: true }));
        await waitForCountRequest();

        expect(mounted?.text()).toContain('Counting…');
        expect(
            mounted?.find('p[aria-live="polite"]').attributes('aria-busy'),
        ).toBe('true');
    });

    it('takes the new count onto the screen when it arrives', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        desktopControls()?.vm.$emit('change', choices({ topics: [5] }));
        await waitForCountRequest();

        const options = get.mock.calls[0]?.[2] as {
            onSuccess: (page: unknown) => void;
        };

        options.onSuccess({
            props: {
                preview: preview({ count: 6, minutes: 4 }),
                topics,
            },
        });
        await flushPromises();

        expect(mounted?.text()).toContain('6 items match');
        expect(mounted?.text()).toContain('About 4 minutes · 6 items match');
        expect(
            mounted?.find('p[aria-live="polite"]').attributes('aria-busy'),
        ).toBe('false');
    });

    it('keeps only the latest answer when an older one arrives late', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        desktopControls()?.vm.$emit('change', choices({ topics: [2] }));
        await waitForCountRequest();
        desktopControls()?.vm.$emit(
            'change',
            choices({ topics: [2], starred: true }),
        );
        await waitForCountRequest();

        const calls = get.mock.calls.map(
            (call) => call[2] as { onSuccess: (page: unknown) => void },
        );

        expect(calls).toHaveLength(2);

        calls[0]?.onSuccess({
            props: { preview: preview({ count: 99 }), topics },
        });
        await flushPromises();

        expect(mounted?.text()).not.toContain('99 items match');

        calls[1]?.onSuccess({
            props: { preview: preview({ count: 7 }), topics },
        });
        await flushPromises();

        expect(mounted?.text()).toContain('7 items match');
    });

    it('keeps the last good number and says it is out of date', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        desktopControls()?.vm.$emit('change', choices({ topics: [2] }));
        await waitForCountRequest();

        const options = get.mock.calls[0]?.[2] as {
            onError: () => void;
        };

        options.onError();
        await flushPromises();

        expect(mounted?.text()).toContain('That count is out of date');
        expect(mounted?.text()).toContain('12 items match');
    });

    it('waits for one request after a run of changes, not one per change', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        mountPage();

        desktopControls()?.vm.$emit('change', choices({ topics: [2] }));
        desktopControls()?.vm.$emit('change', choices({ topics: [2, 5] }));
        desktopControls()?.vm.$emit(
            'change',
            choices({ topics: [2, 5], starred: true }),
        );
        await waitForCountRequest();

        expect(get).toHaveBeenCalledTimes(1);
        expect(get.mock.calls[0]?.[0]).toBe(
            customUrl.url({ query: { topics: '2,5', starred: '1' } }),
        );
    });
});

describe('starting the sitting', () => {
    it('sends the choices as they stand on screen', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        mountPage();

        desktopControls()?.vm.$emit(
            'change',
            choices({ topics: [2], size: 15 }),
        );
        await waitForCountRequest();

        await button('Start custom review')?.trigger('click');

        expect(post).toHaveBeenCalledWith(
            customStore.url(),
            {
                topics: [2],
                collections: [],
                starred: false,
                learned: false,
                size: 15,
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
