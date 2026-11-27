import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import StatTile from '@/components/StatTile.vue';
import Index from '@/pages/progress/Index.vue';
import { create as sessionsCreate } from '@/routes/sessions';
import type { ProgressNumbers } from '@/types';

function numbers(overrides: Partial<ProgressNumbers> = {}): ProgressNumbers {
    return {
        words: 12,
        phrases: 4,
        mistakes: 2,
        grammar_notes: 3,
        practice_days: 9,
        current_streak: 2,
        sessions: 6,
        ...overrides,
    };
}

describe('the Progress page', () => {
    it('shows the session-derived numbers it is given', () => {
        const wrapper = mount(Index, { props: { progress: numbers() } });
        const tiles = wrapper.findAllComponents(StatTile);

        expect(
            tiles
                .find((tile) => tile.props('label') === 'Sessions')
                ?.props('value'),
        ).toBe(6);
        expect(
            tiles
                .find((tile) => tile.props('label') === 'Words')
                ?.props('value'),
        ).toBe(12);
        expect(
            tiles
                .find((tile) => tile.props('label') === 'Phrases')
                ?.props('value'),
        ).toBe(4);
        expect(
            tiles
                .find((tile) => tile.props('label') === 'Grammar')
                ?.props('value'),
        ).toBe(3);
        expect(
            tiles
                .find((tile) => tile.props('label') === 'Total practice days')
                ?.props('value'),
        ).toBe(9);
    });

    it('uses day wording that reads naturally', () => {
        const oneDay = mount(Index, {
            props: { progress: numbers({ current_streak: 1 }) },
        });
        const twoDays = mount(Index, {
            props: { progress: numbers({ current_streak: 2 }) },
        });

        expect(oneDay.text()).toContain('1 day');
        expect(oneDay.text()).not.toContain('1 days');
        expect(twoDays.text()).toContain('2 days');
    });

    it('explains where progress starts when nothing is practised', () => {
        const wrapper = mount(Index, {
            props: {
                progress: numbers({
                    words: 0,
                    phrases: 0,
                    mistakes: 0,
                    grammar_notes: 0,
                    practice_days: 0,
                    current_streak: 0,
                    sessions: 0,
                }),
            },
        });
        const link = wrapper
            .findAll('a')
            .find((anchor) => anchor.text().includes('Start a session'));

        expect(wrapper.text()).toContain('Your progress starts here');
        expect(wrapper.text()).toContain('Complete a conversation');
        expect(wrapper.text()).toContain('Notebook entries');
        expect(link?.attributes('href')).toBe(sessionsCreate.url());
    });

    it('keeps the empty state out of the way once a session is finished', () => {
        const wrapper = mount(Index, { props: { progress: numbers() } });

        expect(wrapper.text()).not.toContain('Your progress starts here');
        expect(wrapper.text()).not.toContain('Start a session');
    });

    it('keeps the empty state out of the way once a day is practised', () => {
        const wrapper = mount(Index, {
            props: {
                progress: numbers({
                    words: 0,
                    phrases: 0,
                    practice_days: 1,
                    current_streak: 1,
                    sessions: 0,
                }),
            },
        });

        expect(wrapper.text()).not.toContain('Your progress starts here');
    });

    it('does not show the old sample chart or summary', () => {
        const wrapper = mount(Index, { props: { progress: numbers() } });

        expect(wrapper.text()).not.toContain('Your common mistakes are fading');
        expect(wrapper.text()).not.toContain('learned 18 new words');
    });
});
