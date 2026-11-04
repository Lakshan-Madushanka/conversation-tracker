import { flushPromises, mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import CollectionMarksSection from '@/components/CollectionMarksSection.vue';
import type { CollectionKindValue, ItemMarkData } from '@/types';

/**
 * The notes save the section creates, with every outcome under the test's
 * control, so no network is involved.
 */
const marksLib = vi.hoisted(() => ({
    saveItemMark: vi.fn(),
    createNotesSaver: vi.fn(),
}));

let resolver: ((outcome: unknown) => void) | null = null;
const saveCalls: Array<Record<string, unknown>> = [];

vi.mock('@/lib/collection-marks', async () => {
    const actual = await vi.importActual<
        typeof import('@/lib/collection-marks')
    >('@/lib/collection-marks');

    return {
        ...actual,
        saveItemMark: marksLib.saveItemMark,
        createNotesSaver: marksLib.createNotesSaver,
    };
});

async function mountSection(
    overrides: Partial<{
        starred: boolean;
        status: 'learned' | 'still_difficult' | null;
        notesHtml: string | undefined;
        notesVersion: number | undefined;
    }> = {},
): Promise<ReturnType<typeof mount>> {
    const props = {
        kind: 'vocabulary' as CollectionKindValue,
        itemKey: 'soup',
        name: 'soup',
        unit: 'word',
        starred: false,
        status: null as 'learned' | 'still_difficult' | null,
        notesHtml: '' as string | undefined,
        notesVersion: 0 as number | undefined,
        ...overrides,
    };

    const mounted = mount(CollectionMarksSection, {
        attachTo: document.body,
        props,
    });

    // The notes editor builds itself over a flush; wait until it exists.
    await flushPromises();
    await flushPromises();

    return mounted;
}

/** Types into the notes editor the way the learner would. */
async function writeNotes(
    mounted: ReturnType<typeof mount>,
    html: string,
): Promise<void> {
    const editor = document.body.querySelector<HTMLElement>(
        'div[contenteditable="true"]',
    );
    editor!.innerHTML = html;
    editor!.dispatchEvent(new Event('input', { bubbles: true }));
    await flushPromises();
}

/** The note save the section asked for, waiting until the test answers. */
beforeEach(() => {
    vi.useFakeTimers();
    saveCalls.splice(0, saveCalls.length);
    resolver = null;
    marksLib.saveItemMark.mockReset();

    marksLib.createNotesSaver.mockImplementation(
        () => async (request: unknown) => {
            saveCalls.push({ ...(request as object) } as Record<
                string,
                unknown
            >);

            return new Promise((resolve) => {
                resolver = (outcome: unknown) => {
                    resolver = null;
                    resolve(outcome);
                };
            });
        },
    );
});

afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('the marks section', () => {
    it('saves the note through the autosave rules', async () => {
        const mounted = await mountSection();

        await writeNotes(mounted, '<p>A note worth keeping.</p>');
        await vi.advanceTimersByTimeAsync(1500);
        await flushPromises();

        expect(saveCalls).toHaveLength(1);
        expect(saveCalls[0]).toMatchObject({
            html: '<p>A note worth keeping.</p>',
            version: 0,
        });

        resolver?.({
            outcome: 'saved',
            saved: { id: 0, version: 1, html: '<p>A note worth keeping.</p>' },
            savedAt: new Date().toISOString(),
        });
        await flushPromises();

        expect(mounted.emitted('mark-saved')?.[0]?.[0]).toMatchObject({
            has_notes: true,
            notes_version: 1,
        });

        mounted.unmount();
    });

    it('offers the two choices when the note changed elsewhere', async () => {
        const mounted = await mountSection();

        await writeNotes(mounted, '<p>My copy.</p>');
        await vi.advanceTimersByTimeAsync(1500);
        await flushPromises();

        resolver?.({
            outcome: 'conflict',
            saved: { id: 0, version: 3, html: '<p>The other copy.</p>' },
        });
        await flushPromises();

        expect(document.body.textContent).toContain(
            'Your note changed in another place',
        );

        const loadLatest = [...document.querySelectorAll('button')].find(
            (button) =>
                button.textContent?.trim() === 'Load the latest version',
        );
        expect(loadLatest).not.toBeNull();

        const keepMine = [...document.querySelectorAll('button')].find(
            (button) => button.textContent?.trim() === 'Keep what I have',
        );
        expect(keepMine).not.toBeNull();

        mounted.unmount();
    });

    it('fits the other note on screen when the learner asks for the latest', async () => {
        const mounted = await mountSection();

        await writeNotes(mounted, '<p>My copy.</p>');
        await vi.advanceTimersByTimeAsync(1500);
        await flushPromises();

        resolver?.({
            outcome: 'conflict',
            saved: { id: 0, version: 3, html: '<p>The other copy.</p>' },
        });
        await flushPromises();

        const loadLatest = [...document.querySelectorAll('button')].find(
            (button) =>
                button.textContent?.trim() === 'Load the latest version',
        );
        loadLatest?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        const marker = (mounted.emitted('mark-saved')?.[0]?.[0] ??
            {}) as ItemMarkData;

        expect(marker.notes_version).toBe(3);
        expect(marker.notes_html).toContain('The other copy.');

        mounted.unmount();
    });

    it('keeps what the learner has and saves it over the other version', async () => {
        const mounted = await mountSection();

        await writeNotes(mounted, '<p>My copy.</p>');
        await vi.advanceTimersByTimeAsync(1500);
        await flushPromises();

        resolver?.({
            outcome: 'conflict',
            saved: { id: 0, version: 3, html: '<p>The other copy.</p>' },
        });
        await flushPromises();

        const keepMine = [...document.querySelectorAll('button')].find(
            (button) => button.textContent?.trim() === 'Keep what I have',
        );
        keepMine?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(saveCalls).toHaveLength(2);
        expect(saveCalls[1]).toMatchObject({
            html: '<p>My copy.</p>',
            version: 3,
        });

        mounted.unmount();
    });

    it('says the star was not saved and offers another try', async () => {
        marksLib.saveItemMark.mockRejectedValueOnce(new Error('offline'));

        const mounted = await mountSection();

        const star = [...document.querySelectorAll('button')].find((button) =>
            button.getAttribute('aria-label')?.startsWith('Star the word'),
        );

        star?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(document.body.textContent).toContain('Your mark was not saved');

        const retry = [...document.querySelectorAll('button')].find(
            (button) => button.textContent?.trim() === 'Try again',
        );
        expect(retry).not.toBeNull();

        marksLib.saveItemMark.mockResolvedValueOnce({
            starred: true,
            status: null,
            has_notes: false,
            notes_version: 0,
            notes_html: '',
        });
        retry?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(mounted.emitted('mark-saved')?.[0]?.[0]).toMatchObject({
            starred: true,
        });
        expect(document.body.textContent).not.toContain(
            'Your mark was not saved',
        );

        mounted.unmount();
    });

    it('says the note was not saved and offers another try when it is too long', async () => {
        const mounted = await mountSection();

        await writeNotes(mounted, `<p>${'x'.repeat(10_005)}</p>`);
        await vi.advanceTimersByTimeAsync(1500);
        await flushPromises();

        resolver?.({
            outcome: 'invalid',
            errors: {
                html: 'A note can hold up to 10,000 characters. Remove a little to save it.',
            },
        });
        await flushPromises();

        expect(document.body.textContent).toContain(
            'A note can hold up to 10,000 characters.',
        );

        const retry = [...document.querySelectorAll('button')].find(
            (button) => button.textContent?.trim() === 'Try again',
        );
        expect(retry).not.toBeNull();

        mounted.unmount();
    });

    it('shows a skeleton while notes are loading and mounts the editor when notes arrive', async () => {
        const mounted = await mountSection({
            notesHtml: undefined,
            notesVersion: undefined,
        });

        expect(document.querySelector('[data-slot="skeleton"]')).not.toBeNull();
        expect(
            document.body.querySelector('div[contenteditable="true"]'),
        ).toBeNull();

        await mounted.setProps({
            notesHtml: '<p>Previously saved note.</p>',
            notesVersion: 4,
        });
        await flushPromises();
        await flushPromises();

        expect(document.querySelector('[data-slot="skeleton"]')).toBeNull();
        const editor = document.body.querySelector(
            'div[contenteditable="true"]',
        );
        expect(editor).not.toBeNull();
        expect(editor?.innerHTML).toContain('Previously saved note.');

        await writeNotes(
            mounted,
            '<p>Previously saved note with new edits.</p>',
        );
        await vi.advanceTimersByTimeAsync(1500);
        await flushPromises();

        expect(saveCalls).toHaveLength(1);
        expect(saveCalls[0]).toMatchObject({
            html: '<p>Previously saved note with new edits.</p>',
            version: 4,
        });

        mounted.unmount();
    });
});
