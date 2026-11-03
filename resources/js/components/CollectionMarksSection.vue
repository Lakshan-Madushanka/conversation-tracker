<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { CircleHelpIcon, TriangleAlertIcon } from '@lucide/vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SaveIndicator from '@/components/SaveIndicator.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useAutoSave } from '@/composables/useAutoSave';
import {
    createNotesSaver,
    saveItemMark,
    starAriaLabel,
    statusAriaLabel,
    type NotesMarker,
    type NotesSnapshot,
} from '@/lib/collection-marks';
import { isRichTextBlank } from '@/lib/rich-text';
import type {
    CollectionItemStatusValue,
    CollectionKindValue,
    ItemMarkData,
} from '@/types';
import CollectionStarToggle from './CollectionStarToggle.vue';
import CollectionStatusMenu from './CollectionStatusMenu.vue';

/**
 * The learner's own marks for one collection item: the star, the status and
 * the personal notes. The star and status go straight to the server and the
 * answer is passed up; the notes save through the same autosave rules the
 * session editor uses — one save at a time, retries, and a conflict the
 * learner resolves between two copies.
 */
const props = defineProps<{
    /** The kind of item the marks belong to. */
    kind: CollectionKindValue;
    /** The item's identity, as the marks endpoints name it. */
    itemKey: string;
    /** The item's name, in words, for the accessible names. */
    name: string;
    /** What one item of this collection is called: word, note, mistake. */
    unit: string;
    /** Whether the star is on right now. */
    starred: boolean;
    /** The chosen status, or null while none is set. */
    status: CollectionItemStatusValue | null;
    /** The saved note as cleaned formatted text. */
    notesHtml: string;
    /** The version the saved note was stored as. */
    notesVersion: number;
}>();

const emit = defineEmits<{
    /** The marks as the server now holds them, after any change. */
    'mark-saved': [mark: ItemMarkData];
}>();

/** Shapes the note the server just stored as a mark patch for the page. */
function notePatch(html: string, version: number): ItemMarkData {
    return {
        starred: props.starred,
        status: props.status,
        has_notes: !isRichTextBlank(html),
        notes_version: version,
        notes_html: html,
    };
}

const notes = ref(props.notesHtml);

const saver = useAutoSave<NotesSnapshot, NotesMarker>({
    snapshot: () => ({ html: notes.value }),
    save: createNotesSaver(props.kind, props.itemKey),
    hasTopic: () => true,
    // An emptied note is still a change worth saving: a stored note has to be
    // cleared on the server. For an item with no mark yet, storing empty
    // words changes nothing.
    hasContent: () => true,
    sessionId: null,
    version: props.notesVersion,
    onSaved: (saved) => {
        emit('mark-saved', notePatch(saved.html, saved.version));
    },
    onLoadLatest: (latest) => {
        notes.value = latest.html;
        emit('mark-saved', notePatch(latest.html, latest.version));
    },
});

const noteInvalid = computed(
    () =>
        saver.status.value === 'failed' &&
        saver.errors.value.html !== undefined,
);

const noteInvalidMessage = computed(
    () =>
        saver.errors.value.html ??
        'Your note was not saved. Nothing changed on the server.',
);

/** The star or status is on its way, so both controls wait for the answer. */
const markSaving = ref(false);

/** The last mark asked for, kept so a failed save can be tried again. */
const pendingMark = ref<{
    starred: boolean;
    status: CollectionItemStatusValue | null;
} | null>(null);

const markFailed = ref(false);

async function saveMark(
    starred: boolean,
    status: CollectionItemStatusValue | null,
): Promise<void> {
    markSaving.value = true;
    markFailed.value = false;
    pendingMark.value = { starred, status };

    try {
        const mark = await saveItemMark(
            props.kind,
            props.itemKey,
            starred,
            status,
        );

        pendingMark.value = null;
        emit('mark-saved', mark);
    } catch {
        // The learner sees a calm message next to the controls; nothing on the
        // server changed, so the controls still show the saved state.
        markFailed.value = true;
    } finally {
        markSaving.value = false;
    }
}

/** Try the same star or status again after a failed save. */
function retryMark(): void {
    const pending = pendingMark.value;

    if (pending !== null) {
        void saveMark(pending.starred, pending.status);
    }
}
</script>

<template>
    <section :aria-label="`Your marks for the ${props.unit} ${props.name}`">
        <Separator class="my-6" />

        <div class="flex flex-wrap items-center gap-2">
            <CollectionStarToggle
                :starred="props.starred"
                :label="starAriaLabel(props.unit, props.name, props.starred)"
                :disabled="markSaving"
                @change="saveMark($event, props.status)"
            />

            <CollectionStatusMenu
                :status="props.status"
                :label="statusAriaLabel(props.unit, props.name)"
                :disabled="markSaving"
                @change="saveMark(props.starred, $event)"
            />
        </div>

        <Alert v-if="markFailed" variant="destructive" class="mt-4">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Your mark was not saved</AlertTitle>
            <AlertDescription>
                Nothing changed on the server. Check your connection and try
                again.
            </AlertDescription>
            <Button
                type="button"
                variant="outline"
                class="mt-2"
                @click="retryMark"
            >
                Try again
            </Button>
        </Alert>

        <div class="mt-6 flex items-baseline justify-between gap-4">
            <h3 class="text-sm font-medium">Your notes</h3>
            <SaveIndicator
                :status="saver.status.value"
                :last-saved-at="saver.lastSavedAt.value"
            />
        </div>

        <RichTextEditor
            v-model="notes"
            variant="full"
            :label="`Notes about the ${props.unit} ${props.name}`"
            :placeholder="`Write your own notes about this ${props.unit}…`"
            class="mt-2"
        />

        <Alert v-if="saver.status.value === 'conflict'" class="mt-4">
            <CircleHelpIcon aria-hidden="true" />
            <AlertTitle>Your note changed in another place</AlertTitle>
            <AlertDescription>
                This note was saved somewhere else while you edited it. Load the
                latest version, or keep the copy you have.
            </AlertDescription>
            <div class="flex flex-wrap gap-2">
                <Button type="button" @click="saver.loadLatest()">
                    Load the latest version
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="saver.keepMine()"
                >
                    Keep what I have
                </Button>
            </div>
        </Alert>

        <Alert v-else-if="noteInvalid" variant="destructive" class="mt-4">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Your note was not saved</AlertTitle>
            <AlertDescription>{{ noteInvalidMessage }}</AlertDescription>
            <Button
                type="button"
                variant="outline"
                class="mt-2"
                @click="saver.retry()"
            >
                Try again
            </Button>
        </Alert>
    </section>
</template>
