<script setup lang="ts">
import { computed, ref } from 'vue';
import { TriangleAlertIcon } from '@lucide/vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import {
    saveItemMark,
    starAriaLabel,
    statusAriaLabel,
} from '@/lib/collection-marks';
import type {
    CollectionItemStatusValue,
    CollectionKindValue,
    ItemMarkData,
} from '@/types';
import CollectionNotesEditor from './CollectionNotesEditor.vue';
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
    notesHtml?: string;
    /** The version the saved note was stored as. */
    notesVersion?: number;
}>();

const emit = defineEmits<{
    /** The marks as the server now holds them, after any change. */
    'mark-saved': [mark: ItemMarkData];
}>();

const notesLoaded = computed(
    () => props.notesHtml !== undefined && props.notesVersion !== undefined,
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

        <Alert v-if="markFailed" variant="destructive" class="mt-4 w-full">
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

        <CollectionNotesEditor
            v-if="notesLoaded"
            :kind="props.kind"
            :item-key="props.itemKey"
            :name="props.name"
            :unit="props.unit"
            :starred="props.starred"
            :status="props.status"
            :notes-html="props.notesHtml!"
            :notes-version="props.notesVersion!"
            @mark-saved="emit('mark-saved', $event)"
        />

        <div v-else class="mt-6 space-y-2">
            <div class="flex items-baseline justify-between gap-4">
                <h3 class="text-sm font-medium">Your notes</h3>
            </div>
            <Skeleton class="h-28 w-full rounded-md" />
        </div>
    </section>
</template>
