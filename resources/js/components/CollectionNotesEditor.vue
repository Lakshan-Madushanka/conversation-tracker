<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CircleHelpIcon, TriangleAlertIcon } from '@lucide/vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SaveIndicator from '@/components/SaveIndicator.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useAutoSave } from '@/composables/useAutoSave';
import {
    createNotesSaver,
    type NotesMarker,
    type NotesSnapshot,
} from '@/lib/collection-marks';
import { isRichTextBlank } from '@/lib/rich-text';
import type {
    CollectionItemStatusValue,
    CollectionKindValue,
    ItemMarkData,
} from '@/types';

/**
 * The personal notes editor for one collection item.
 *
 * Saves through the same autosave rules the session editor uses: debounce,
 * retries, conflict detection via version marker, and graceful recovery.
 */
const props = defineProps<{
    kind: CollectionKindValue;
    itemKey: string;
    name: string;
    unit: string;
    starred: boolean;
    status: CollectionItemStatusValue | null;
    notesHtml: string;
    notesVersion: number;
}>();

const emit = defineEmits<{
    'mark-saved': [mark: ItemMarkData];
}>();

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

watch(
    () => props.notesVersion,
    (nextVersion) => {
        if (nextVersion !== undefined && nextVersion !== saver.version.value) {
            saver.version.value = nextVersion;
        }
    },
);

watch(
    () => props.notesHtml,
    (nextHtml) => {
        if (!saver.hasUnsavedChanges.value && nextHtml !== notes.value) {
            notes.value = nextHtml;
        }
    },
);

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
</script>

<template>
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
            <p>
                This note was saved somewhere else while you edited it. Load the
                latest version, or keep the copy you have.
            </p>

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
        </AlertDescription>
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
</template>
