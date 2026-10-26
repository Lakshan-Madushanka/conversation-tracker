import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import SaveNotices from '@/components/SaveNotices.vue';
import type { PracticeSessionData } from '@/types';

function conflictSession(): PracticeSessionData {
    return {
        id: 5,
        status: 'unfinished',
        finished_at: null,
        last_changed_at: null,
        archived_at: null,
        version: 3,
        topic: { id: 1, name: 'Travel' },
        questions: [],
    };
}

function button(wrapper: ReturnType<typeof mount>, text: string) {
    return wrapper
        .findAll('button')
        .find((candidate) => candidate.text().includes(text));
}

describe('a save refused because the session was archived', () => {
    it('keeps the learner informed with the server’s words', () => {
        const wrapper = mount(SaveNotices, {
            props: {
                conflict: null,
                status: 'archived',
                failureMessage: '',
                archivedMessage:
                    'This session was archived. Restore it to keep editing.',
            },
        });

        expect(wrapper.get('[role="alert"]').text()).toContain(
            'This session is archived.',
        );
        expect(wrapper.text()).toContain(
            'This session was archived. Restore it to keep editing.',
        );
        expect(button(wrapper, 'Restore session')).toBeDefined();
    });

    it('has its own calm fallback when the server sends no words', () => {
        const wrapper = mount(SaveNotices, {
            props: {
                conflict: null,
                status: 'archived',
                failureMessage: '',
            },
        });

        expect(wrapper.text()).toContain(
            'Your text is still here. Restore the session to keep editing.',
        );
    });

    it('asks to restore the session', async () => {
        const wrapper = mount(SaveNotices, {
            props: {
                conflict: null,
                status: 'archived',
                failureMessage: '',
            },
        });

        await button(wrapper, 'Restore session')?.trigger('click');

        expect(wrapper.emitted('restore')).toHaveLength(1);
    });

    it('explains the archive rather than a conflict it also sees', () => {
        const wrapper = mount(SaveNotices, {
            props: {
                conflict: conflictSession(),
                status: 'archived',
                failureMessage: '',
            },
        });

        expect(wrapper.text()).toContain('This session is archived.');
        expect(wrapper.text()).not.toContain(
            'This session was changed somewhere else.',
        );
        expect(button(wrapper, 'Load latest version')).toBeUndefined();
    });
});
