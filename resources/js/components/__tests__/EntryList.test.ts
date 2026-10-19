import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import EntryList from '@/components/EntryList.vue';
import { MAX_ENTRIES, MAX_LENGTH } from '@/lib/list-entries';
import type { FeedbackEntry } from '@/types';

let wrapper: ReturnType<typeof mountEntryList> | null = null;

/** Build a paste event carrying plain text, as a real clipboard would. */
function pasteEvent(text: string, html = ''): Event {
    const event = new Event('paste', { bubbles: true, cancelable: true });

    Object.defineProperty(event, 'clipboardData', {
        value: {
            getData: (type: string) => (type === 'text/html' ? html : text),
        },
    });

    return event;
}

function mountEntryList(entries: FeedbackEntry[] = []) {
    const component = mount(EntryList, {
        attachTo: document.body,
        props: {
            entries,
            label: 'New vocabulary for question 1',
            fieldId: 'field-vocabulary',
            placeholder: 'e.g. reservation',
        },
    });

    wrapper = component;

    return component;
}

function addField(component: ReturnType<typeof mountEntryList>) {
    return component.get('#field-vocabulary');
}

/** The text sitting in the add field, which is a multi-line text area. */
function addFieldText(component: ReturnType<typeof mountEntryList>): string {
    return (addField(component).element as HTMLTextAreaElement).value;
}

async function pasteInto(
    component: ReturnType<typeof mountEntryList>,
    target: Element,
    text: string,
): Promise<void> {
    target.dispatchEvent(pasteEvent(text));
    await component.vm.$nextTick();
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('pasting a list', () => {
    it('adds one entry per pasted line and offers an undo', async () => {
        const component = mountEntryList();

        await pasteInto(
            component,
            addField(component).element,
            '• reservation\n2) journey\n\n- catch up\n',
        );

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            expect.objectContaining({ text: 'reservation' }),
            expect.objectContaining({ text: 'journey' }),
            expect.objectContaining({ text: 'catch up' }),
        ]);

        expect(component.text()).toContain('Pasted 3 lines, 3 entries added.');
        expect(component.text()).toContain('Undo');

        await component
            .get('button[aria-label="Undo the paste"]')
            .trigger('click');

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([]);
    });

    it('announces a paste that repeats words already in the list', async () => {
        const component = mountEntryList([{ id: 'e1', text: 'reservation' }]);

        await pasteInto(
            component,
            addField(component).element,
            'reservation\njourney',
        );

        expect(component.get('[role="status"]').text()).toContain(
            '1 of them is already in this list.',
        );
    });

    it('leaves a single pasted line to the browser', async () => {
        const component = mountEntryList();
        const event = pasteEvent('reservation');

        addField(component).element.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(component.emitted('update:entries')).toBeUndefined();
    });
});

describe('the limits of a box', () => {
    it('keeps the pasted text when a line is too long', async () => {
        const component = mountEntryList();

        await pasteInto(
            component,
            addField(component).element,
            `short\n${'a'.repeat(MAX_LENGTH + 1)}`,
        );

        expect(component.emitted('update:entries')).toBeUndefined();
        expect(addFieldText(component)).toContain('short');
        expect(component.get('[role="status"]').text()).toContain(
            `Each entry can be up to ${MAX_LENGTH} characters.`,
        );
        expect(component.get('[role="alert"]').text()).toContain(
            `Each entry can be up to ${MAX_LENGTH} characters.`,
        );
    });

    it('keeps the pasted text when the box is already full', async () => {
        const full = Array.from({ length: MAX_ENTRIES }, (_, index) => ({
            id: `e${index}`,
            text: `entry ${index}`,
        }));

        const component = mountEntryList(full);

        await pasteInto(component, addField(component).element, 'one\ntwo');

        expect(component.emitted('update:entries')).toBeUndefined();
        expect(addFieldText(component)).toBe('one\ntwo');
        expect(component.get('[role="status"]').text()).toContain(
            `A box can hold up to ${MAX_ENTRIES} entries.`,
        );
        expect(component.get('[role="alert"]').text()).toContain(
            `A box can hold up to ${MAX_ENTRIES} entries.`,
        );
    });
});

describe('adding an entry', () => {
    it('adds what is typed into the add field on Enter', async () => {
        const component = mountEntryList();

        await addField(component).setValue('reservation');
        await addField(component).trigger('keydown', { key: 'Enter' });

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            expect.objectContaining({ text: 'reservation' }),
        ]);
        expect(addFieldText(component)).toBe('');
    });

    it('splits several typed lines into entries', async () => {
        const component = mountEntryList();

        await addField(component).setValue('one\ntwo');
        await addField(component).trigger('keydown', { key: 'Enter' });

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toHaveLength(
            2,
        );
    });
});

describe('editing an entry', () => {
    it('joins pasted lines into the entry being edited', async () => {
        const component = mountEntryList([{ id: 'e1', text: 'reservation' }]);

        await component
            .get('button[aria-label^="Edit entry 1"]')
            .trigger('click');
        await component.vm.$nextTick();

        const edit = component.get('#field-vocabulary-edit-e1');
        await edit.setValue('');
        await pasteInto(component, edit.element, 'a long\nway\noff');

        expect((edit.element as HTMLInputElement).value).toBe('a long way off');

        await edit.trigger('keydown', { key: 'Enter' });

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            { id: 'e1', text: 'a long way off' },
        ]);
    });

    it('removes an entry that is edited down to nothing, with an undo', async () => {
        const component = mountEntryList([
            { id: 'e1', text: 'first' },
            { id: 'e2', text: 'second' },
        ]);

        await component
            .get('button[aria-label^="Edit entry 2"]')
            .trigger('click');
        await component.vm.$nextTick();

        const edit = component.get('#field-vocabulary-edit-e2');
        await edit.setValue('   ');
        await edit.trigger('keydown', { key: 'Enter' });

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            { id: 'e1', text: 'first' },
        ]);
        expect(component.text()).toContain('Entry removed.');

        await component
            .get('button[aria-label="Undo the removal"]')
            .trigger('click');

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            { id: 'e1', text: 'first' },
            { id: 'e2', text: 'second' },
        ]);
    });
});

describe('removing an entry', () => {
    it('removes it at once and offers an undo', async () => {
        const component = mountEntryList([
            { id: 'e1', text: 'first' },
            { id: 'e2', text: 'second' },
        ]);

        await component
            .get('button[aria-label="Remove entry 2: second"]')
            .trigger('click');

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            { id: 'e1', text: 'first' },
        ]);

        await component
            .get('button[aria-label="Undo the removal"]')
            .trigger('click');

        expect(component.emitted('update:entries')?.at(-1)?.[0]).toEqual([
            { id: 'e1', text: 'first' },
            { id: 'e2', text: 'second' },
        ]);
    });
});

describe('duplicates', () => {
    it('flags the later of two entries with the same text', () => {
        const component = mountEntryList([
            { id: 'e1', text: 'reservation' },
            { id: 'e2', text: '  RESERVATION ' },
            { id: 'e3', text: 'journey' },
        ]);

        const tags = component.findAll('li').map((item) => item.text());

        expect(tags[1]).toContain('Duplicate');
        expect(tags[0]).not.toContain('Duplicate');
        expect(tags[2]).not.toContain('Duplicate');
    });

    it('does not flag the same text in another box', () => {
        const component = mountEntryList([{ id: 'e1', text: 'reservation' }]);

        expect(component.text()).not.toContain('Duplicate');
    });
});
