<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { PlusIcon, Trash2Icon } from '@lucide/vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import UndoNotice from '@/components/UndoNotice.vue';
import { Button } from '@/components/ui/button';
import { focusElementById } from '@/lib/dom';
import { newNote } from '@/lib/feedback';
import { isRichTextBlank } from '@/lib/rich-text';
import type { GrammarNote } from '@/types';

/**
 * The grammar notes box: many formatted notes, one per paste, never split by
 * line. A note the learner leaves empty is not kept.
 */
const props = defineProps<{
    notes: GrammarNote[];
    /** Accessible name for the notes, for example "Grammar notes for question 1". */
    label: string;
    /** Unique id for the note editors, so focus can be moved to them. */
    fieldId: string;
    placeholder?: string;
    error?: string;
    /**
     * Guided mode: opens the box with an empty note ready for the paste, so
     * there is always one blank editor to write into and never more.
     */
    guided?: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:notes', notes: GrammarNote[]): void;
}>();

/** Set after a note is removed, until it is undone or replaced. */
const notice = ref<{ message: string } | null>(null);
/** Words repeated into the live region so a screen reader hears what happened. */
const announcement = ref('');

/** The notes as they were before the last removal, for undo. */
let restoreTo: GrammarNote[] | null = null;

const hasDraft = computed(() =>
    props.notes.some((note) => isRichTextBlank(note.html)),
);

defineExpose({ hasDraft });

function replaceNotes(notes: GrammarNote[]): void {
    emit('update:notes', notes);
}

function say(message: string): void {
    announcement.value = message;
}

function noteFieldId(note: GrammarNote): string {
    return `${props.fieldId}-${note.id}`;
}

/** Add one empty note and put the cursor in it, ready to type or paste. */
function addNote(): void {
    const note = newNote('');

    replaceNotes([...props.notes, note]);
    say(
        'New note added. It is empty, so it will not be kept until you type in it.',
    );

    void nextTick(() => focusElementById(noteFieldId(note)));
}

/**
 * Hand the learner an editor the moment guided mode shows this box: focus the
 * empty note already waiting, or add one when every note holds something.
 */
function prepareGuidedNote(): void {
    const last = props.notes[props.notes.length - 1];

    if (last !== undefined && isRichTextBlank(last.html)) {
        void nextTick(() => focusElementById(noteFieldId(last)));

        return;
    }

    addNote();
}

onMounted(() => {
    if (props.guided) {
        prepareGuidedNote();
    }
});

/**
 * Keep the notes the learner actually wrote. An empty note is dropped once it
 * loses focus, so a box is never saved full of blank editors.
 */
function updateNote(id: string, html: string): void {
    replaceNotes(
        props.notes.map((note) => (note.id === id ? { ...note, html } : note)),
    );
}

function dropEmptyNote(id: string): void {
    const note = props.notes.find((candidate) => candidate.id === id);

    if (!note || !isRichTextBlank(note.html)) {
        return;
    }

    replaceNotes(props.notes.filter((candidate) => candidate.id !== id));
}

function removeNote(id: string): void {
    restoreTo = [...props.notes];
    replaceNotes(props.notes.filter((note) => note.id !== id));

    notice.value = { message: 'Note removed.' };
    say('Note removed. Undo is available.');
}

function undo(): void {
    if (restoreTo === null) {
        return;
    }

    replaceNotes(restoreTo);
    restoreTo = null;
    notice.value = null;
    say('Note restored.');
}

/** Ctrl/Cmd+Z puts back the last removed note while the notice is up. */
function handleKeyDown(event: KeyboardEvent): void {
    if (notice.value === null || !(event.ctrlKey || event.metaKey)) {
        return;
    }

    if (event.key.toLowerCase() !== 'z' || event.shiftKey) {
        return;
    }

    event.preventDefault();
    undo();
}
</script>

<template>
    <div
        :id="props.fieldId"
        tabindex="-1"
        class="space-y-3 outline-none"
        @keydown="handleKeyDown"
    >
        <p role="status" aria-live="polite" class="sr-only">
            {{ announcement }}
        </p>

        <div
            v-for="(note, index) in props.notes"
            :key="note.id"
            class="space-y-1.5"
        >
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-medium text-muted-foreground">
                    Note {{ index + 1 }}
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="min-h-11 min-w-11 text-destructive"
                    :aria-label="`Remove note ${index + 1} of ${props.label}`"
                    @click="removeNote(note.id)"
                >
                    <Trash2Icon aria-hidden="true" />
                </Button>
            </div>

            <div @focusout="dropEmptyNote(note.id)">
                <RichTextEditor
                    :id="noteFieldId(note)"
                    :model-value="note.html"
                    :label="`Note ${index + 1} of ${props.label}`"
                    :placeholder="props.placeholder"
                    @update:model-value="updateNote(note.id, $event)"
                />
            </div>
        </div>

        <UndoNotice
            v-if="notice"
            :message="notice.message"
            undo-label="Undo the removal"
            @undo="undo"
        />

        <Button type="button" variant="outline" size="lg" @click="addNote">
            <PlusIcon aria-hidden="true" />
            Add note
        </Button>

        <p
            v-if="props.error"
            role="alert"
            class="text-xs font-medium text-destructive"
        >
            {{ props.error }}
        </p>
    </div>
</template>
