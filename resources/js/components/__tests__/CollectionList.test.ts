import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import CollectionList from '@/components/CollectionList.vue';
import { create as sessionsCreate } from '@/routes/sessions';
import type {
    CollectionAppearance,
    CollectionItem,
    CollectionMistakeAppearance,
    CollectionMistakeItem,
    CollectionNoteItem,
} from '@/types';

function appearance(): CollectionAppearance {
    return {
        session_id: 1,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        entry_id: 'entry-1',
        entry_position: 0,
        meaning: 'a liquid food',
        example: 'I want a soup.',
        example_html: '',
    };
}

function noteItem(
    overrides: Partial<CollectionNoteItem> = {},
): CollectionNoteItem {
    return {
        key: 'note-7',
        text: 'Use the past tense here.',
        preview: 'Use the past tense here. && <safe>',
        html: '<p>Use the <strong>past tense</strong> here.</p>',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        session_id: 1,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        entry_id: null,
        entry_position: 0,
        ...overrides,
    };
}

function item(overrides: Partial<CollectionItem> = {}): CollectionItem {
    return {
        key: 'soup',
        title: 'soup',
        meaning: 'a liquid food',
        example: 'I want a soup.',
        example_html: '',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 1,
        needs_attention: false,
        attention_reason: null,
        appearances: [appearance()],
        ...overrides,
    };
}

function mistakeAppearance(
    overrides: Partial<CollectionMistakeAppearance> = {},
): CollectionMistakeAppearance {
    return {
        session_id: 1,
        session_date: '2026-10-19T10:14:55.000000Z',
        topic: 'Ordering at a restaurant',
        question_position: 1,
        entry_id: 'entry-1',
        entry_position: 0,
        said: 'I am agree',
        correction: 'I agree',
        correction_html: '',
        reason: 'Word order',
        ...overrides,
    };
}

function mistakeItem(
    overrides: Partial<CollectionMistakeItem> = {},
): CollectionMistakeItem {
    return {
        key: 'mistake-agree',
        title: 'I am agree',
        correction: 'I agree',
        correction_html: '',
        reason: 'Word order',
        latest_session_date: '2026-10-19T10:14:55.000000Z',
        seen_count: 2,
        worth_practicing: false,
        needs_attention: false,
        attention_reason: null,
        appearances: [mistakeAppearance()],
        ...overrides,
    };
}

function startLink(wrapper: ReturnType<typeof mount>) {
    return wrapper
        .findAll('a')
        .find((anchor) => anchor.text().includes('Start a session'));
}

describe('the collection list', () => {
    it('shows a word with its meaning and example', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [item()], total: 1 },
        });

        expect(wrapper.text()).toContain('soup');
        expect(wrapper.text()).toContain('a liquid food');
        expect(wrapper.text()).toContain('“I want a soup.”');
    });

    it('says how often a word was seen only when it was seen more than once', () => {
        const once = mount(CollectionList, {
            props: { items: [item()], total: 1 },
        });

        expect(once.text()).not.toContain('Seen 1 times');

        const many = mount(CollectionList, {
            props: { items: [item({ seen_count: 3 })], total: 1 },
        });

        expect(many.text()).toContain('Seen 3 times');
    });

    it('asks for a meaning when none was saved', () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [
                    item({
                        meaning: '',
                        example: '',
                        needs_attention: true,
                    }),
                ],
                total: 1,
            },
        });

        expect(wrapper.text()).toContain('Needs attention');
        expect(wrapper.text()).toContain('Add the meaning');
    });

    it('opens the word that was chosen', async () => {
        const chosen = item();

        const wrapper = mount(CollectionList, {
            props: { items: [chosen], total: 1 },
        });

        const word = wrapper
            .findAll('button')
            .find((button) => button.text() === 'soup');

        await word?.trigger('click');

        expect(wrapper.emitted('open')?.[0]).toEqual([chosen]);
    });

    it('offers another page and says how much is shown', async () => {
        const wrapper = mount(CollectionList, {
            props: { items: [item()], hasMore: true, total: 30 },
        });

        expect(wrapper.text()).toContain('Showing 1 of 30');

        const button = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Load more words'));

        await button?.trigger('click');

        expect(wrapper.emitted('load-more')).toHaveLength(1);
    });

    it('says when there is nothing left to load', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [item()], total: 1 },
        });

        expect(wrapper.text()).toContain('That is every word — 1 in total.');
    });

    it('shows placeholders while the next page arrives', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [], loading: true },
        });

        expect(wrapper.text()).not.toContain('No words yet');
        expect(
            wrapper.findAll('[data-slot="skeleton"]').length,
        ).toBeGreaterThan(0);
    });

    it('shows placeholders while another tab loads', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [], updating: true },
        });

        expect(wrapper.text()).not.toContain('No words yet');
        expect(
            wrapper.findAll('[data-slot="skeleton"]').length,
        ).toBeGreaterThan(0);
    });

    it('explains a failed load and offers to try again', async () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [item()],
                error: true,
                hasMore: true,
                total: 30,
            },
        });

        expect(wrapper.text()).toContain('Could not load the collection');

        const retry = wrapper
            .findAll('button')
            .find((element) => element.text().includes('Try again'));

        await retry?.trigger('click');

        expect(wrapper.emitted('load-more')).toHaveLength(1);
    });

    it('offers a fresh start when no session has been finished', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [], total: 0, hasFinishedSessions: false },
        });

        expect(wrapper.text()).toContain('Your collections are waiting');
        expect(startLink(wrapper)?.attributes('href')).toBe(
            sessionsCreate.url(),
        );
    });

    it('says no words yet when there are finished sessions without words', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [], total: 0, hasFinishedSessions: true },
        });

        expect(wrapper.text()).toContain('No words yet');
        expect(startLink(wrapper)?.attributes('href')).toBe(
            sessionsCreate.url(),
        );
    });

    it('lists grammar notes by their preview, topic and date', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [noteItem()], tab: 'grammar', total: 1 },
        });

        expect(wrapper.text()).toContain('Note');
        expect(wrapper.text()).toContain('Topic');
        expect(wrapper.text()).toContain('Date');
        expect(wrapper.text()).toContain('Use the past tense here. && <safe>');
        expect(wrapper.text()).toContain('Ordering at a restaurant');
        // The list never shows the note's markup or a seen counter.
        expect(wrapper.text()).not.toContain('strong');
        expect(wrapper.text()).not.toContain('Seen');
    });

    it('opens the note that was chosen', async () => {
        const chosen = noteItem();
        const wrapper = mount(CollectionList, {
            props: { items: [chosen], tab: 'grammar', total: 1 },
        });

        const row = wrapper
            .findAll('button')
            .find((button) =>
                button.text().includes('Use the past tense here.'),
            );

        await row?.trigger('click');

        expect(wrapper.emitted('open')?.[0]).toEqual([chosen]);
    });

    it('calls grammar notes by their own empty state', () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [],
                tab: 'grammar',
                total: 0,
                hasFinishedSessions: true,
            },
        });

        expect(wrapper.text()).toContain('No grammar notes yet');
    });

    it('shows a phrase example with its saved bold and italic', () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [
                    item({
                        example_html: 'I <strong>caught</strong> up.',
                    }),
                ],
                tab: 'phrases',
                total: 1,
            },
        });

        expect(wrapper.html()).toContain('I <strong>caught</strong> up.');
    });
});

describe('the mistakes list', () => {
    it('shows what was said, the correction and the reason', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [mistakeItem()], tab: 'mistakes', total: 1 },
        });

        expect(wrapper.text()).toContain('What I said');
        expect(wrapper.text()).toContain('Correction');
        expect(wrapper.text()).toContain('Why');
        expect(wrapper.text()).toContain('I am agree');
        expect(wrapper.text()).toContain('I agree');
        expect(wrapper.text()).toContain('Word order');
    });

    it('says when a mistake has no reason and asks for the correction', () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [mistakeItem({ reason: '', correction: '' })],
                tab: 'mistakes',
                total: 1,
            },
        });

        expect(wrapper.text()).toContain('No reason added');
        expect(wrapper.text()).toContain('Add the correction');
    });

    it("keeps the correction's bold and italic and never its markup as words", () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [
                    mistakeItem({
                        correction: 'I agree',
                        correction_html: '<strong>I agree</strong>',
                    }),
                ],
                tab: 'mistakes',
                total: 1,
            },
        });

        expect(wrapper.html()).toContain('<strong>I agree</strong>');
    });

    it('labels a mistake worth practicing once it repeats', () => {
        const calm = mount(CollectionList, {
            props: { items: [mistakeItem()], tab: 'mistakes', total: 1 },
        });

        expect(calm.text()).not.toContain('Worth practicing');

        const worth = mount(CollectionList, {
            props: {
                items: [mistakeItem({ worth_practicing: true })],
                tab: 'mistakes',
                total: 1,
            },
        });

        expect(worth.text()).toContain('Worth practicing');
    });

    it('says how often the mistake was seen, even the first time', () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [mistakeItem({ seen_count: 1 })],
                tab: 'mistakes',
                total: 1,
            },
        });

        expect(wrapper.text()).toContain('Seen 1 time');

        const many = mount(CollectionList, {
            props: {
                items: [mistakeItem({ seen_count: 4 })],
                tab: 'mistakes',
                total: 1,
            },
        });

        expect(many.text()).toContain('Seen 4 times');
    });

    it('marks a mistake that needs attention', () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [
                    mistakeItem({
                        needs_attention: true,
                        attention_reason: 'Add the correction.',
                        correction: '',
                    }),
                ],
                tab: 'mistakes',
                total: 1,
            },
        });

        expect(wrapper.text()).toContain('Needs attention');
    });

    it('opens the mistake that was chosen', async () => {
        const chosen = mistakeItem();

        const wrapper = mount(CollectionList, {
            props: { items: [chosen], tab: 'mistakes', total: 1 },
        });

        const said = wrapper
            .findAll('button')
            .find((button) => button.text() === 'I am agree');

        await said?.trigger('click');

        expect(wrapper.emitted('open')?.[0]).toEqual([chosen]);
    });

    it('names the mistake for screen readers by what it holds', () => {
        const wrapper = mount(CollectionList, {
            props: { items: [mistakeItem()], tab: 'mistakes', total: 1 },
        });

        const row = wrapper.find('[aria-label^="Open mistake:"]');

        expect(row?.attributes('aria-label')).toBe(
            'Open mistake: I am agree, correction: I agree',
        );
    });

    it('calls mistakes by their own empty state', () => {
        const wrapper = mount(CollectionList, {
            props: {
                items: [],
                tab: 'mistakes',
                total: 0,
                hasFinishedSessions: true,
            },
        });

        expect(wrapper.text()).toContain('Nothing here yet');
    });
});
