import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import GrammarNotesList from '@/components/GrammarNotesList.vue';
import type { GrammarNote } from '@/types';

let wrapper: ReturnType<typeof mountNotes> | null = null;

/**
 * Wait a real animation frame, so deferred work (focus retries) has run.
 */
function animationFrame(): Promise<void> {
    return new Promise((resolve) => {
        requestAnimationFrame(() => {
            resolve();
        });
    });
}

function mountNotes(
    notes: GrammarNote[] = [],
    extra: Record<string, unknown> = {},
) {
    const component = mount(GrammarNotesList, {
        attachTo: document.body,
        props: {
            notes,
            label: 'Grammar notes for question 1',
            fieldId: 'field-grammar',
            placeholder: 'Explain the grammar point…',
            ...extra,
        },
    });

    wrapper = component;

    return component;
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

/** Find a button by the text it shows, since the test DOM has no :has-text(). */
function buttonByText(component: ReturnType<typeof mountNotes>, text: string) {
    const found = component
        .findAll('button')
        .find((candidate) => candidate.text().includes(text));

    if (found === undefined) {
        throw new Error(`No button showing "${text}".`);
    }

    return found;
}

describe('notes', () => {
    it('gives every note its own editor', () => {
        const component = mountNotes([
            { id: 'n1', html: '<p>Use the past tense.</p>' },
            { id: 'n2', html: '<p>Articles come before nouns.</p>' },
        ]);

        expect(component.findAll('.rich-text-editor')).toHaveLength(2);
        // `get` throws when a button is missing, so finding both is enough.
        expect(
            component
                .get('button[aria-label^="Remove note 1"]')
                .attributes('aria-label'),
        ).toContain('Remove note 1');
        expect(
            component
                .get('button[aria-label^="Remove note 2"]')
                .attributes('aria-label'),
        ).toContain('Remove note 2');
        expect(component.emitted('update:notes')).toBeUndefined();
    });

    it('adds one empty note at a time', async () => {
        const component = mountNotes();

        await buttonByText(component, 'Add note').trigger('click');
        await component.vm.$nextTick();

        const added = component
            .emitted('update:notes')
            ?.at(-1)?.[0] as GrammarNote[];

        expect(added).toHaveLength(1);
        expect(added[0]?.html).toBe('');
        expect(added[0]?.id).toBeTruthy();

        await component.setProps({ notes: added });

        await buttonByText(component, 'Add note').trigger('click');
        await component.vm.$nextTick();

        const two = component
            .emitted('update:notes')
            ?.at(-1)?.[0] as GrammarNote[];

        expect(two).toHaveLength(2);
    });

    it('keeps a multi line note as one note', async () => {
        const component = mountNotes([
            { id: 'n1', html: '<p>First line</p><p>Second line</p>' },
        ]);

        // One editor holds the whole note, including its line break.
        expect(component.findAll('.rich-text-editor')).toHaveLength(1);

        await buttonByText(component, 'Add note').trigger('click');
        await component.vm.$nextTick();

        const notes = component
            .emitted('update:notes')
            ?.at(-1)?.[0] as GrammarNote[];

        expect(notes).toHaveLength(2);
        expect(notes[0]?.html).toBe('<p>First line</p><p>Second line</p>');
        expect(notes[1]?.html).toBe('');
    });

    it('removes a note at once and offers an undo', async () => {
        const component = mountNotes([
            { id: 'n1', html: '<p>Keep me</p>' },
            { id: 'n2', html: '<p>Remove me</p>' },
        ]);

        await component
            .get(
                'button[aria-label="Remove note 2 of Grammar notes for question 1"]',
            )
            .trigger('click');

        expect(component.emitted('update:notes')?.at(-1)?.[0]).toEqual([
            { id: 'n1', html: '<p>Keep me</p>' },
        ]);
        expect(component.text()).toContain('Note removed.');

        await component
            .get('button[aria-label="Undo the removal"]')
            .trigger('click');

        expect(component.emitted('update:notes')?.at(-1)?.[0]).toEqual([
            { id: 'n1', html: '<p>Keep me</p>' },
            { id: 'n2', html: '<p>Remove me</p>' },
        ]);
    });

    it('drops a note the learner leaves empty when focus moves away', async () => {
        const component = mountNotes([{ id: 'n1', html: '' }]);

        await component.find('.rich-text-editor').trigger('focusout');
        await component.vm.$nextTick();

        expect(component.emitted('update:notes')?.at(-1)?.[0]).toEqual([]);
    });

    it('keeps a note that holds something', async () => {
        const component = mountNotes([
            { id: 'n1', html: '<p>Use the past tense.</p>' },
        ]);

        await component.find('.rich-text-editor').trigger('focusout');
        await component.vm.$nextTick();

        expect(component.emitted('update:notes')).toBeUndefined();
    });
});

describe('guided mode', () => {
    it('opens with one empty note, cursor in it, ready for the paste', async () => {
        const component = mountNotes([], { guided: true });

        const added = component
            .emitted('update:notes')
            ?.at(0)?.[0] as GrammarNote[];

        expect(added).toHaveLength(1);
        expect(added[0]?.html).toBe('');

        await component.setProps({ notes: added });
        await component.vm.$nextTick();
        await animationFrame();

        expect(document.activeElement?.id).toBe(
            `field-grammar-${added[0]?.id}`,
        );
    });

    it('offers the blank note that is already waiting instead of another', async () => {
        const component = mountNotes([{ id: 'n1', html: '' }], {
            guided: true,
        });

        expect(component.emitted('update:notes')).toBeUndefined();

        await animationFrame();

        expect(document.activeElement?.id).toBe('field-grammar-n1');
    });

    it('adds a fresh note when every note already holds something', async () => {
        const component = mountNotes(
            [{ id: 'n1', html: '<p>Use the past tense.</p>' }],
            { guided: true },
        );

        const added = component
            .emitted('update:notes')
            ?.at(0)?.[0] as GrammarNote[];

        expect(added).toHaveLength(2);
        expect(added[1]?.html).toBe('');
    });
});
