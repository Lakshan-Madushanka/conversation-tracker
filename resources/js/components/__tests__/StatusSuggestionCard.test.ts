import { nextTick } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import StatusSuggestionCard from '@/components/review/StatusSuggestionCard.vue';
import { suggestionConfirm, suggestionDecline } from '@/routes/review';
import type { StatusSuggestionData } from '@/types';

/** Every post the card makes, so the test can answer it the way the server would. */
const posts: {
    url: string;
    data: Record<string, unknown>;
    options: Record<string, (value?: unknown) => void>;
}[] = [];

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    useForm: (initial: Record<string, unknown>) => ({
        processing: false,
        data: initial,
        post: (
            url: string,
            options: Record<string, (value?: unknown) => void>,
        ) => {
            posts.push({ url, data: initial, options });
        },
    }),
}));

function suggestion(
    overrides: Partial<StatusSuggestionData> = {},
): StatusSuggestionData {
    return {
        kind: 'vocabulary',
        item_identity: 'soup',
        status: 'learned',
        status_label: 'Learned',
        rating_label: 'Easy',
        run: 3,
        text: '“soup” has been easy three times in a row.',
        run_started_id: 18,
        ...overrides,
    };
}

function mountCard(
    props: Partial<{
        suggestion: StatusSuggestionData;
        defaultFocus: boolean;
    }> = {},
) {
    return mount(StatusSuggestionCard, {
        attachTo: document.body,
        props: {
            reviewId: 41,
            suggestion: suggestion(),
            ...props,
        },
    });
}

function button(wrapper: ReturnType<typeof mountCard>, label: string) {
    return wrapper.findAll('button').find((item) => item.text() === label);
}

beforeEach(() => {
    posts.length = 0;
});

afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('what the card says', () => {
    it('shows the item and the run of ratings behind the offer', () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain(
            '“soup” has been easy three times in a row.',
        );
        expect(wrapper.text()).toContain(
            "You've rated this easy 3 times in a row. Would you like to mark it as learned?",
        );
    });

    it('says once when the run is a single rating', () => {
        const wrapper = mountCard({ suggestion: suggestion({ run: 1 }) });

        expect(wrapper.text()).toContain('rated this easy once in a row');
    });

    it('uses the hard words for a still-difficult offer', () => {
        const wrapper = mountCard({
            suggestion: suggestion({
                status: 'still_difficult',
                status_label: 'Still difficult',
                rating_label: 'Hard',
                run: 2,
                text: '“I am agree” has been hard twice in a row.',
            }),
        });

        expect(wrapper.text()).toContain(
            'Would you like to mark it as still difficult?',
        );
        expect(button(wrapper, 'Mark as still difficult')?.exists()).toBe(true);
    });

    it('changes nothing before the learner answers', () => {
        mountCard();

        expect(posts).toHaveLength(0);
    });
});

describe('marking the item', () => {
    it('posts the offer to the confirm route with the run that earned it', async () => {
        const wrapper = mountCard();

        await button(wrapper, 'Mark as learned')?.trigger('click');

        expect(posts[0]?.url).toBe(suggestionConfirm.url({ review: 41 }));
        expect(posts[0]?.data).toEqual({
            kind: 'vocabulary',
            item_identity: 'soup',
            status: 'learned',
            run_started_id: 18,
        });
    });

    it('settles on the marked state once the server agrees', async () => {
        const wrapper = mountCard();

        await button(wrapper, 'Mark as learned')?.trigger('click');
        posts[0]?.options.onSuccess?.({});
        await flushPromises();

        expect(wrapper.text()).toContain('Marked as learned.');
        expect(wrapper.text()).not.toContain('Not now');
        expect(button(wrapper, 'Mark as learned')).toBeUndefined();
    });

    it('keeps asking when the save fails', async () => {
        const wrapper = mountCard();

        await button(wrapper, 'Mark as learned')?.trigger('click');
        posts[0]?.options.onError?.({ status: 'learned' });
        await flushPromises();

        expect(wrapper.text()).toContain('Mark as learned');
        expect(wrapper.text()).not.toContain('Marked as learned.');
    });
});

describe('a quiet "Not now"', () => {
    it('posts to the decline route and changes no status', async () => {
        const wrapper = mountCard();

        await button(wrapper, 'Not now')?.trigger('click');

        expect(posts[0]?.url).toBe(suggestionDecline.url({ review: 41 }));
        expect(posts[0]?.data).toEqual({
            kind: 'vocabulary',
            item_identity: 'soup',
            status: 'learned',
            run_started_id: 18,
        });
    });

    it('says the item stays as it is once recorded', async () => {
        const wrapper = mountCard();

        await button(wrapper, 'Not now')?.trigger('click');
        posts[0]?.options.onSuccess?.({});
        await flushPromises();

        expect(wrapper.text()).toContain('Not now — this stays as it is.');
        expect(wrapper.text()).toContain('A new run of ratings can ask again.');
        expect(wrapper.text()).not.toContain('Mark as learned');
    });

    it('keeps the reason line out once the learner has answered', async () => {
        const wrapper = mountCard();

        await button(wrapper, 'Not now')?.trigger('click');
        posts[0]?.options.onSuccess?.({});
        await flushPromises();

        expect(wrapper.text()).not.toContain(
            'Would you like to mark it as learned?',
        );
    });
});

describe('where focus lands', () => {
    it('puts focus on "Not now" for the first card only', async () => {
        const wrapper = mountCard({ defaultFocus: true });
        await nextTick();
        await flushPromises();

        expect(document.activeElement?.textContent?.trim()).toBe('Not now');

        wrapper.unmount();
    });

    it('leaves focus alone on the second card', async () => {
        const wrapper = mountCard({ defaultFocus: false });
        await nextTick();
        await flushPromises();

        expect(document.activeElement?.textContent?.trim()).not.toBe('Not now');

        wrapper.unmount();
    });
});
