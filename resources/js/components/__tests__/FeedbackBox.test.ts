import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import FeedbackBox from '@/components/FeedbackBox.vue';
import { newEntry, newNote } from '@/lib/feedback';
import type { FeedbackType } from '@/types';

/**
 * Wait a real animation frame, so deferred work (focus retries, the
 * "opening" flag clearing) has run.
 */
function animationFrame(): Promise<void> {
    return new Promise((resolve) => {
        requestAnimationFrame(() => {
            resolve();
        });
    });
}

function mountBox(
    props: Partial<{
        type: FeedbackType;
        modelValue: string;
        fieldId: string;
        questionNumber: number;
        error: string;
    }> = {},
) {
    const type = props.type ?? 'mistake';

    return mount(FeedbackBox, {
        attachTo: document.body,
        props: {
            ...props,
            type,
            // Each kind of box holds a different shape: plain text, entries,
            // or notes. The text here stands in for their content.
            modelValue: valueFor(type, props.modelValue ?? ''),
            fieldId: 'field-mistake',
            questionNumber: 1,
        },
    });
}

/** Turn plain text into the list entries a box holds. */
function entriesOf(text: string) {
    return text === '' ? [] : [newEntry(text)];
}

/** Turn plain text into the notes the grammar box holds. */
function notesOf(text: string) {
    return text === '' ? [] : [newNote(text)];
}

/** The value a box of this kind holds, built from plain text. */
function valueFor(type: FeedbackType, text: string) {
    if (type === 'natural_version') {
        return text;
    }

    if (type === 'grammar_note') {
        return notesOf(text);
    }

    return entriesOf(text);
}

/** The field the learner types into, whatever kind of box it is. */
function field(wrapper: ReturnType<typeof mountBox>) {
    return wrapper.find('#field-mistake');
}

/** What is sitting in the field, before it has been added as an entry. */
function typedText(wrapper: ReturnType<typeof mountBox>): string {
    return (field(wrapper).element as HTMLTextAreaElement).value;
}

/**
 * Whether that field is showing. A shrunk box keeps its fields mounted, so
 * "the box is closed" means hidden, not absent from the DOM.
 */
function fieldIsShown(wrapper: ReturnType<typeof mountBox>): boolean {
    const found = field(wrapper);

    return found.exists() && found.isVisible();
}

/**
 * Whether the box is open, read from its border: an open box is solid, a
 * shrunk one is dashed. Used where there is no single field to look for, since
 * the notes box holds one editor per note rather than one field.
 */
function boxIsOpen(wrapper: ReturnType<typeof mountBox>): boolean {
    return wrapper.classes().includes('bg-card');
}

describe('collapsed trigger', () => {
    it('is a real button so it is reachable with the keyboard', () => {
        const wrapper = mountBox();
        const trigger = wrapper.get('button');

        expect(trigger.attributes('type')).toBe('button');
        expect(trigger.attributes('aria-expanded')).toBe('false');

        wrapper.unmount();
    });

    it('holds off building the fields until the box is opened', () => {
        const wrapper = mountBox();

        // A question carries five boxes. Building all of their editors up
        // front would cost a Tiptap instance each, opened or not.
        expect(field(wrapper).exists()).toBe(false);

        wrapper.unmount();
    });

    it('opens the box and moves focus into it', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        expect(fieldIsShown(wrapper)).toBe(true);
        expect(document.activeElement?.id).toBe('field-mistake');

        wrapper.unmount();
    });
});

describe('focus handling', () => {
    it('stays open when the removed trigger fires a focusout while opening', async () => {
        const wrapper = mountBox();
        const trigger = wrapper.get('button');

        trigger.element.focus();
        // A native click, so the re-render that removes the trigger has not
        // happened yet when the event below is dispatched.
        trigger.element.click();

        // Chromium fires a bubbling focusout (with no new focus target) when
        // the focused trigger loses focus during the re-render. That must not
        // be mistaken for the user leaving the box.
        trigger.element.dispatchEvent(
            new FocusEvent('focusout', { bubbles: true }),
        );

        await wrapper.vm.$nextTick();
        await animationFrame();

        expect(fieldIsShown(wrapper)).toBe(true);

        wrapper.unmount();
    });

    it('stays open when focus moves to an element inside the box', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        field(wrapper).element.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: wrapper.element,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(fieldIsShown(wrapper)).toBe(true);

        wrapper.unmount();
    });

    it('collapses an empty box when focus moves to an element outside', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        field(wrapper).element.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: document.body,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(fieldIsShown(wrapper)).toBe(false);

        wrapper.unmount();
    });

    it('collapses an empty box when focus lands nowhere, as on a background click', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        // Let opening settle, or the guard that protects the open from its own
        // re-render swallows this event too.
        await animationFrame();

        field(wrapper).element.dispatchEvent(
            new FocusEvent('focusout', { bubbles: true }),
        );
        await wrapper.vm.$nextTick();

        expect(fieldIsShown(wrapper)).toBe(false);

        wrapper.unmount();
    });

    it('keeps a box with content open no matter where focus goes', async () => {
        const wrapper = mountBox({ modelValue: 'I go → I went' });

        expect(fieldIsShown(wrapper)).toBe(true);

        field(wrapper).element.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: document.body,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(fieldIsShown(wrapper)).toBe(true);

        wrapper.unmount();
    });

    it('opens and keeps a box that carries an error', async () => {
        const wrapper = mountBox({ error: 'This box is required' });

        expect(fieldIsShown(wrapper)).toBe(true);
        expect(wrapper.find('[role="alert"]').text()).toBe(
            'This box is required',
        );

        wrapper.unmount();
    });
});

describe('shrinking a box', () => {
    it('offers the close button even while the box holds entries', async () => {
        const wrapper = mountBox({ modelValue: 'I go → I went' });

        const close = wrapper.get('button[aria-label^="Close"]');

        await close.trigger('click');
        await wrapper.vm.$nextTick();

        expect(fieldIsShown(wrapper)).toBe(false);

        wrapper.unmount();
    });

    it('keeps the entries it holds, so nothing is lost by shrinking', async () => {
        const wrapper = mountBox({ modelValue: 'I go → I went' });

        await wrapper.get('button[aria-label^="Close"]').trigger('click');
        await wrapper.vm.$nextTick();

        // The entry is still mounted, just hidden, so it survives a shrink.
        expect(field(wrapper).exists()).toBe(true);
        expect(wrapper.text()).toContain('I go → I went');

        wrapper.unmount();
    });

    it('says how much it holds once it is shrunk', async () => {
        const wrapper = mountBox({ modelValue: 'I go → I went' });

        await wrapper.get('button[aria-label^="Close"]').trigger('click');
        await wrapper.vm.$nextTick();

        // A shrunk box still tells the learner what is inside it.
        expect(wrapper.text()).toContain('1 entry');
        expect(wrapper.text()).not.toContain('Add corrections');

        wrapper.unmount();
    });

    it('reopens where it was, with its entries ready to edit', async () => {
        const wrapper = mountBox({ modelValue: 'I go → I went' });

        const close = wrapper.get('button[aria-label^="Close"]');
        await close.trigger('click');
        await wrapper.vm.$nextTick();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        expect(fieldIsShown(wrapper)).toBe(true);
        expect(document.activeElement?.id).toBe('field-mistake');

        wrapper.unmount();
    });

    it('keeps text typed into the add field when shrunk and reopened', async () => {
        const wrapper = mountBox();

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        // Typed but not yet added, so the box still counts as empty.
        await field(wrapper).setValue('I goed');
        expect(typedText(wrapper)).toBe('I goed');

        // If shrinking unmounted the fields again, this text would be gone.
        await wrapper.get('button[aria-label^="Close"]').trigger('click');
        await wrapper.vm.$nextTick();
        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        expect(fieldIsShown(wrapper)).toBe(true);
        expect(typedText(wrapper)).toBe('I goed');

        wrapper.unmount();
    });

    it('keeps the hint for a box that is genuinely empty', () => {
        const wrapper = mountBox();

        expect(wrapper.text()).toContain('Add corrections');

        wrapper.unmount();
    });
});

describe('the grammar notes box', () => {
    it('opens and takes focus inside, like the other kinds of box', async () => {
        const wrapper = mountBox({ type: 'grammar_note' });

        expect(boxIsOpen(wrapper)).toBe(false);

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        // Focus has to land inside the box, or the next click anywhere reads as
        // the learner leaving and the box shuts itself again.
        expect(boxIsOpen(wrapper)).toBe(true);
        expect(document.activeElement?.id).toBe('field-mistake');
    });

    it('stays open when focus moves within it', async () => {
        const wrapper = mountBox({ type: 'grammar_note' });

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        const addNote = wrapper
            .findAll('button')
            .find((candidate) => candidate.text().includes('Add note'));

        expect(addNote).toBeDefined();

        addNote!.element.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: wrapper.element,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(boxIsOpen(wrapper)).toBe(true);
    });

    it('stays open when focus moves within it with no related target', async () => {
        const wrapper = mountBox({ type: 'grammar_note' });

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        const addNote = wrapper
            .findAll('button')
            .find((candidate) => candidate.text().includes('Add note'));

        expect(addNote).toBeDefined();

        // Firefox omits relatedTarget on focusout. The box must look at where
        // focus actually is, or it reads every click inside as a way out.
        addNote!.element.focus();
        addNote!.element.dispatchEvent(
            new FocusEvent('focusout', { bubbles: true }),
        );
        await wrapper.vm.$nextTick();

        expect(boxIsOpen(wrapper)).toBe(true);
    });

    it('stays open after it holds a note', async () => {
        const wrapper = mountBox({
            type: 'grammar_note',
            modelValue: 'Use the past tense here.',
        });

        expect(boxIsOpen(wrapper)).toBe(true);

        await wrapper.get('button[aria-label^="Close"]').trigger('click');
        await wrapper.vm.$nextTick();

        expect(boxIsOpen(wrapper)).toBe(false);

        await wrapper.get('button').trigger('click');
        await wrapper.vm.$nextTick();
        await animationFrame();

        expect(boxIsOpen(wrapper)).toBe(true);
    });
});
