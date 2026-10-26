import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import BackToLibrary from '@/components/BackToLibrary.vue';
import { defaultLibraryFilters } from '@/lib/library-filters';
import { saveLibrarySnapshot } from '@/lib/library';
import { index as sessionsIndex } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

function session(): SessionCardData {
    return {
        id: 1,
        topic: 'Travel',
        session_date: '2026-10-12T09:00:00.000000Z',
        last_edited_at: null,
        archived_at: null,
        status: 'finished',
        counts: {
            questions: 1,
            words: 1,
            phrases: 1,
            mistakes: 1,
            grammar_notes: 1,
        },
    };
}

function link(wrapper: ReturnType<typeof mount>) {
    return wrapper.get('a');
}

afterEach(() => {
    window.sessionStorage.clear();
});

describe('the way back to the Library', () => {
    it('names the Library by default', () => {
        const wrapper = mount(BackToLibrary);

        expect(link(wrapper).text()).toBe('Back to Library');
        expect(link(wrapper).attributes('href')).toBe(sessionsIndex.url());
    });

    it('names the Archived view when there is no saved list', () => {
        const wrapper = mount(BackToLibrary, {
            props: { archived: true },
        });

        expect(link(wrapper).text()).toBe('Back to Archived');
        expect(link(wrapper).attributes('href')).toBe(
            sessionsIndex.url({ query: { view: 'archived' } }),
        );
    });

    it('returns to the archived list and filters it came from', () => {
        saveLibrarySnapshot({
            sessions: [session()],
            nextCursor: null,
            hasMore: false,
            total: 1,
            allTotal: 1,
            archivedTotal: 1,
            filters: {
                ...defaultLibraryFilters(),
                view: 'archived',
                topics: [2],
            },
            timezone: 'UTC',
            scrollY: 120,
            savedAt: Date.now(),
        });

        const wrapper = mount(BackToLibrary, {
            props: { archived: false },
        });

        expect(link(wrapper).text()).toBe('Back to Archived');
        expect(link(wrapper).attributes('href')).toContain('view=archived');
        expect(link(wrapper).attributes('href')).toContain('topics=2');
    });

    it('returns to the Library list and filters it came from', () => {
        saveLibrarySnapshot({
            sessions: [session()],
            nextCursor: null,
            hasMore: false,
            total: 1,
            allTotal: 1,
            archivedTotal: 0,
            filters: {
                ...defaultLibraryFilters(),
                topics: [2],
            },
            timezone: 'UTC',
            scrollY: 120,
            savedAt: Date.now(),
        });

        const wrapper = mount(BackToLibrary);

        expect(link(wrapper).text()).toBe('Back to Library');
        expect(link(wrapper).attributes('href')).toContain('topics=2');
        expect(link(wrapper).attributes('href')).not.toContain('view=archived');
    });
});
