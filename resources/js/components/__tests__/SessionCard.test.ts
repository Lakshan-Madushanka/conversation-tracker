import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import SessionCard from '@/components/SessionCard.vue';
import { shortDate } from '@/lib/format';
import { edit as sessionsEdit, show as sessionsShow } from '@/routes/sessions';
import type { SearchMatch, SessionCardData } from '@/types';

afterEach(() => {
    document.body.innerHTML = '';
});

function session(overrides: Partial<SessionCardData> = {}): SessionCardData {
    return {
        id: 7,
        topic: 'Ordering at a restaurant',
        session_date: '2026-10-12T09:00:00.000000Z',
        last_edited_at: null,
        archived_at: null,
        status: 'finished',
        counts: {
            questions: 6,
            words: 12,
            phrases: 4,
            mistakes: 4,
            grammar_notes: 2,
        },
        ...overrides,
    };
}

function mountCard(card: SessionCardData = session()) {
    return mount(SessionCard, { props: { session: card } });
}

describe('a session in a list', () => {
    it('shows its topic, the day it happened, what it holds and its status', () => {
        const wrapper = mountCard();
        const date = shortDate('2026-10-12T09:00:00.000000Z');

        // Dates are formatted in the learner's own locale, so the same
        // formatter is used here rather than a fixed expectation.
        expect(date).not.toBe('');
        expect(wrapper.text()).toContain('Ordering at a restaurant');
        expect(wrapper.text()).toContain(
            '6 questions · 12 words · 4 phrases · 4 mistakes',
        );
        expect(wrapper.text()).toContain(date);
        expect(wrapper.text()).toContain('Finished');
    });

    it('counts a single one of anything as one, not ones', () => {
        const wrapper = mountCard(
            session({
                counts: {
                    questions: 1,
                    words: 1,
                    phrases: 1,
                    mistakes: 1,
                    grammar_notes: 1,
                },
            }),
        );

        expect(wrapper.text()).toContain(
            '1 question · 1 word · 1 phrase · 1 mistake',
        );
    });

    it('opens the page where a finished session can be read', () => {
        const wrapper = mountCard();

        expect(wrapper.get('a').attributes('href')).toBe(sessionsShow.url(7));
    });

    it('opens the editor for a session that is not finished', () => {
        const wrapper = mountCard(
            session({ status: 'unfinished', last_edited_at: null }),
        );

        expect(wrapper.text()).toContain('Unfinished');
        expect(wrapper.get('a').attributes('href')).toBe(sessionsEdit.url(7));
    });

    it('says when a session was edited on a later day', () => {
        const edited = '2026-10-14T09:00:00.000000Z';
        const wrapper = mountCard(session({ last_edited_at: edited }));
        const time = wrapper
            .findAll('time')
            .find((element) => element.text().includes('Edited'));

        expect(wrapper.text()).toContain('Edited');
        expect(wrapper.text()).toContain(shortDate(edited));
        expect(time?.attributes('title')).toBe(`Edited ${shortDate(edited)}`);
    });

    it('leaves the edited line off when the edit happened on the same day', () => {
        const wrapper = mountCard(
            session({ last_edited_at: '2026-10-12T18:00:00.000000Z' }),
        );

        expect(wrapper.text()).not.toContain('Edited');
    });

    it('shows only the counts when there is no date to read', () => {
        const wrapper = mountCard(session({ session_date: null }));

        expect(wrapper.findAll('time')).toHaveLength(0);
        expect(wrapper.text()).toContain('6 questions');
    });
});

describe('a session that was archived', () => {
    it('shows Archived instead of its finished state', () => {
        const wrapper = mountCard(
            session({ archived_at: '2026-10-20T09:00:00.000000Z' }),
        );

        expect(wrapper.text()).toContain('Archived');
        expect(wrapper.text()).not.toContain('Finished');
    });

    it('still opens the reading page', () => {
        const wrapper = mountCard(
            session({ archived_at: '2026-10-20T09:00:00.000000Z' }),
        );

        expect(wrapper.get('a').attributes('href')).toBe(sessionsShow.url(7));
    });

    it('offers Restore, and emits the session it belongs to', async () => {
        const card = session({ archived_at: '2026-10-20T09:00:00.000000Z' });
        const wrapper = mount(SessionCard, {
            attachTo: document.body,
            props: { session: card },
        });

        (
            wrapper.get('button[aria-haspopup="menu"]').element as HTMLElement
        ).click();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const items = [
            ...document.querySelectorAll<HTMLElement>('[role="menuitem"]'),
        ];

        expect(items.map((item) => item.textContent?.trim())).toEqual([
            'Restore',
            'Delete',
        ]);

        items[0]?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('restore')?.[0]).toEqual([card]);
        expect(wrapper.emitted('archive')).toBeUndefined();

        wrapper.unmount();
    });

    it('emits the session when Delete is chosen', async () => {
        const card = session({ archived_at: '2026-10-20T09:00:00.000000Z' });
        const wrapper = mount(SessionCard, {
            attachTo: document.body,
            props: { session: card },
        });

        (
            wrapper.get('button[aria-haspopup="menu"]').element as HTMLElement
        ).click();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const remove = [
            ...document.querySelectorAll<HTMLElement>('[role="menuitem"]'),
        ].find((item) => item.textContent?.includes('Delete'));

        remove?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('delete')?.[0]).toEqual([card]);
        expect(wrapper.emitted('restore')).toBeUndefined();

        wrapper.unmount();
    });

    it('archives a finished session from the card menu', async () => {
        const card = session();
        const wrapper = mount(SessionCard, {
            attachTo: document.body,
            props: { session: card },
        });

        (
            wrapper.get('button[aria-haspopup="menu"]').element as HTMLElement
        ).click();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const archive = [
            ...document.querySelectorAll<HTMLElement>('[role="menuitem"]'),
        ].find((item) => item.textContent?.includes('Archive'));

        archive?.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('archive')?.[0]).toEqual([card]);

        wrapper.unmount();
    });

    it('can be shown without actions, as it is on Home', () => {
        const wrapper = mount(SessionCard, {
            props: { session: session(), showMenu: false },
        });

        expect(wrapper.find('button[aria-haspopup="menu"]').exists()).toBe(
            false,
        );
        expect(wrapper.get('a').attributes('href')).toBe(sessionsShow.url(7));
    });
});

function match(overrides: Partial<SearchMatch> = {}): SearchMatch {
    return {
        key: 'entry:vocabulary:v1',
        kind: 'entry',
        type: 'vocabulary',
        question_position: 2,
        entry_id: 'v1',
        entry_position: 0,
        segments: [
            { text: 'A warm ', match: false },
            { text: 'soup', match: true },
        ],
        ...overrides,
    };
}

function matches(count: number): SearchMatch[] {
    return Array.from({ length: count }, (_, index) =>
        match({
            key: `entry:vocabulary:v${index + 1}`,
            entry_id: `v${index + 1}`,
            entry_position: index,
            segments: [{ text: `soup ${index + 1}`, match: true }],
        }),
    );
}

describe('a session card while searching', () => {
    it('highlights the part of the topic that matched', () => {
        const wrapper = mountCard(
            session({
                topic: 'Ordering at a restaurant',
                topic_segments: [
                    { text: 'Ordering at a ', match: false },
                    { text: 'restaurant', match: true },
                ],
            }),
        );

        expect(wrapper.get('mark').text()).toBe('restaurant');
        expect(wrapper.text()).toContain('Ordering at a restaurant');
    });

    it('lists the matching items with their section and question number', () => {
        const wrapper = mountCard(
            session({
                match_count: 1,
                matches: [match()],
            }),
        );

        expect(wrapper.text()).toContain('New vocabulary');
        expect(wrapper.text()).toContain('Question 2');
        expect(wrapper.text()).toContain('A warm soup');
        expect(wrapper.get('mark').text()).toBe('soup');
    });

    it('shows five items first, then offers the rest', async () => {
        const wrapper = mountCard(
            session({ match_count: 7, matches: matches(7) }),
        );

        expect(wrapper.findAll('mark')).toHaveLength(5);
        expect(wrapper.text()).toContain('Show 2 more items');

        const more = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Show 2 more items'));

        await more?.trigger('click');

        expect(wrapper.findAll('mark')).toHaveLength(7);
        expect(wrapper.text()).not.toContain('Show 2 more items');
    });

    it('says when the server stopped at its limit', () => {
        const wrapper = mountCard(
            session({
                match_count: 60,
                matches: matches(50),
            }),
        );

        expect(wrapper.text()).toContain(
            'Showing the first 50 matching items. There are 10 more in this session.',
        );
    });

    it('opens each matching item on its own, never nested in the card link', () => {
        const wrapper = mountCard(
            session({ match_count: 1, matches: [match()] }),
        );

        const links = wrapper.findAll('a');

        expect(links).toHaveLength(2);
        expect(wrapper.find('a a').exists()).toBe(false);

        const item = links[1]!;
        const params = new URL(
            item.attributes('href') ?? '',
            'http://localhost',
        ).searchParams;

        expect(item.attributes('href')).toContain(sessionsShow.url(7));
        expect(params.get('question')).toBe('2');
        expect(params.get('field')).toBe('entry');
        expect(params.get('type')).toBe('vocabulary');
        expect(params.get('entry')).toBe('v1');
    });

    it('sends an unfinished session to its editor at the item', () => {
        const wrapper = mountCard(
            session({
                status: 'unfinished',
                match_count: 1,
                matches: [match()],
            }),
        );

        const item = wrapper.findAll('a')[1];

        expect(item?.attributes('href')).toContain(sessionsEdit.url(7));
    });

    it('folds the items again when a new search replaces them', async () => {
        const wrapper = mountCard(
            session({ match_count: 7, matches: matches(7) }),
        );

        const more = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Show 2 more items'));

        await more?.trigger('click');

        expect(wrapper.findAll('mark')).toHaveLength(7);

        await wrapper.setProps({
            session: session({ match_count: 2, matches: matches(2) }),
        });

        expect(wrapper.findAll('mark')).toHaveLength(2);
        expect(wrapper.text()).not.toContain('Show 2 more items');
    });
});
