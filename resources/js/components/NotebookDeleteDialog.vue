<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { LoaderCircleIcon, Trash2Icon, TriangleAlertIcon } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    deleteNotebookPermanently,
    fetchNotebookDeletePreview,
} from '@/lib/notebook-delete';
import type {
    NotebookDeletionCounts,
    NotebookDeletionPreview,
} from '@/types/notebook';

/**
 * The one confirmation a notebook is deleted through.
 *
 * It reads what the server would remove, so the warning names the notebook and
 * shows exact numbers: saved rows and the distinct study items they make,
 * because duplicates can make the two differ. Affected saved reviews are named
 * with how many items each loses, and Delete stays disabled until the learner
 * ticks that they understand nothing comes back.
 *
 * If the notebook changed while the warning was open, the confirmation is
 * refused: the fresh numbers replace the old ones and the tick is cleared, so
 * nothing is deleted under numbers the learner has not seen.
 */
const props = defineProps<{
    /** Whether the warning is on screen. */
    open: boolean;
    /** The notebook being deleted. */
    notebookId: number;
    /** Used to name the notebook while the preview is still loading. */
    notebookName: string;
    /** Used to say where the notebook lives while the preview loads. */
    notebookTopic: string;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'deleted', notebook: { id: number; name: string }): void;
    (event: 'missing', notebookId: number): void;
}>();

const preview = ref<NotebookDeletionPreview | null>(null);
const loading = ref(false);
const failed = ref(false);
const deleting = ref(false);
const acknowledged = ref(false);
const staleMessage = ref('');

/** The id of the Cancel button, so the safe choice can take the first focus. */
const CANCEL_ID = 'notebook-delete-cancel';

/** Guards a slow preview for a warning that has since been closed. */
let generation = 0;

const shownName = computed(
    () => preview.value?.notebook.name ?? props.notebookName,
);

const shownTopic = computed(
    () => preview.value?.notebook.topic ?? props.notebookTopic,
);

function itemCount(value: number): string {
    return `${value} ${value === 1 ? 'study item' : 'study items'}`;
}

/**
 * A saved row is what the learner typed; a study item is one distinct thing.
 * They are shown side by side only when duplicates make them different.
 */
function countText(saved: number, items: number): string {
    if (saved === items) {
        return String(saved);
    }

    return `${saved} saved ${saved === 1 ? 'row' : 'rows'} · ${itemCount(items)}`;
}

const countRows = computed(() => {
    const source = preview.value;

    if (source === null) {
        return [];
    }

    const saved: NotebookDeletionCounts = source.counts;
    const items: NotebookDeletionCounts = source.items;

    return [
        {
            label: 'Words',
            value: countText(saved.words, items.words),
        },
        {
            label: 'Phrases',
            value: countText(saved.phrases, items.phrases),
        },
        {
            label: 'Grammar notes',
            value: countText(saved.grammar_notes, items.grammar_notes),
        },
    ];
});

const affectedReviews = computed(() => preview.value?.reviews ?? []);

const canConfirm = computed(
    () => preview.value !== null && !deleting.value && acknowledged.value,
);

function close(): void {
    if (deleting.value) {
        return;
    }

    emit('update:open', false);
}

function handleOpenChange(value: boolean): void {
    if (!value) {
        close();

        return;
    }

    emit('update:open', true);
}

function reset(): void {
    generation += 1;
    preview.value = null;
    loading.value = false;
    failed.value = false;
    deleting.value = false;
    acknowledged.value = false;
    staleMessage.value = '';
}

async function loadPreview(): Promise<void> {
    const run = ++generation;

    preview.value = null;
    loading.value = true;
    failed.value = false;
    acknowledged.value = false;
    staleMessage.value = '';

    const outcome = await fetchNotebookDeletePreview(props.notebookId);

    if (run !== generation || !props.open) {
        return;
    }

    loading.value = false;

    if (outcome.outcome === 'preview') {
        preview.value = outcome.preview;
        await focusCancel();

        return;
    }

    if (outcome.outcome === 'missing') {
        emit('missing', props.notebookId);
        emit('update:open', false);

        return;
    }

    failed.value = true;
    await focusCancel();
}

/**
 * The safe choice gets the first focus, even when the "I understand" tick is
 * the first control in the dialog's reading order.
 */
async function focusCancel(): Promise<void> {
    await nextTick();

    document.getElementById(CANCEL_ID)?.focus();
}

function focusCancelFirst(event: Event): void {
    event.preventDefault();
    void focusCancel();
}

async function confirmDelete(): Promise<void> {
    if (!canConfirm.value || preview.value === null) {
        return;
    }

    deleting.value = true;
    staleMessage.value = '';

    const outcome = await deleteNotebookPermanently(
        props.notebookId,
        preview.value.token,
    );

    deleting.value = false;

    if (outcome.outcome === 'deleted') {
        emit('deleted', {
            id: outcome.result.notebook.id,
            name: outcome.result.notebook.name,
        });
        emit('update:open', false);

        return;
    }

    if (outcome.outcome === 'stale') {
        preview.value = outcome.preview;
        acknowledged.value = false;
        staleMessage.value = outcome.message;
        await focusCancel();

        return;
    }

    if (outcome.outcome === 'missing') {
        emit('missing', props.notebookId);
        emit('update:open', false);

        return;
    }

    toast.error('We could not delete this notebook. Nothing was changed.');
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            void loadPreview();

            return;
        }

        reset();
    },
    { immediate: true },
);
</script>

<template>
    <AlertDialog :open="props.open" @update:open="handleOpenChange">
        <AlertDialogContent
            class="sm:max-w-xl!"
            @open-auto-focus="focusCancelFirst"
        >
            <AlertDialogHeader>
                <AlertDialogTitle>Delete this notebook?</AlertDialogTitle>
                <AlertDialogDescription>
                    <span class="font-medium text-foreground">
                        {{ shownName }}
                    </span>
                    <template v-if="shownTopic !== ''">
                        in {{ shownTopic }}</template
                    >
                    and everything in it will be removed permanently.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <p v-if="loading" class="flex items-center gap-2 text-sm">
                <LoaderCircleIcon
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                Checking what will be removed…
            </p>

            <div v-else-if="failed">
                <Alert variant="destructive">
                    <TriangleAlertIcon aria-hidden="true" />
                    <AlertTitle>We could not check this notebook</AlertTitle>
                    <AlertDescription>
                        <p>
                            Nothing has been deleted. Check your connection and
                            try again.
                        </p>
                    </AlertDescription>
                </Alert>
                <Button
                    type="button"
                    variant="outline"
                    class="mt-3"
                    @click="loadPreview"
                >
                    Try again
                </Button>
            </div>

            <template v-else-if="preview !== null">
                <Alert
                    v-if="staleMessage !== ''"
                    variant="destructive"
                    class="mb-3"
                >
                    <TriangleAlertIcon aria-hidden="true" />
                    <AlertTitle>This notebook changed</AlertTitle>
                    <AlertDescription>
                        <p>{{ staleMessage }}</p>
                        <p class="mt-1">
                            The numbers below are fresh. Check them and confirm
                            again.
                        </p>
                    </AlertDescription>
                </Alert>

                <Alert variant="destructive" class="mb-3">
                    <TriangleAlertIcon aria-hidden="true" />
                    <AlertTitle>This cannot be undone.</AlertTitle>
                    <AlertDescription>
                        <p>
                            The notebook and its saved words, phrases, and
                            grammar notes will leave Notebooks, Collections, and
                            Review. Other notebooks and sessions are not
                            affected, even when they contain the same words.
                        </p>
                    </AlertDescription>
                </Alert>

                <section
                    aria-labelledby="notebook-delete-counts-title"
                    class="rounded-lg border p-3"
                >
                    <h3
                        id="notebook-delete-counts-title"
                        class="text-sm font-medium"
                    >
                        What will be removed
                    </h3>

                    <dl class="mt-2 space-y-1 text-sm">
                        <div
                            v-for="row in countRows"
                            :key="row.label"
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ row.value }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="affectedReviews.length > 0"
                    aria-labelledby="notebook-delete-reviews-title"
                    class="mt-3 rounded-lg border p-3"
                >
                    <h3
                        id="notebook-delete-reviews-title"
                        class="text-sm font-medium"
                    >
                        Saved reviews that lose items
                    </h3>

                    <ul class="mt-2 space-y-2 text-sm">
                        <li
                            v-for="review in affectedReviews"
                            :key="review.id"
                            class="break-words"
                        >
                            <span class="font-medium text-foreground">
                                {{ review.name }}
                            </span>
                            <span class="text-muted-foreground">
                                — {{ review.removed }} of
                                {{ review.removed + review.remaining }}
                                {{ review.removed === 1 ? 'item' : 'items' }}
                                removed.
                                <template v-if="review.becomes_empty">
                                    This review will become Empty.
                                </template>
                            </span>
                        </li>
                    </ul>

                    <p class="mt-2 text-xs text-muted-foreground">
                        Their attempt history stays. Answers already practised
                        keep their Deleted marks.
                    </p>
                </section>

                <div class="mt-4 flex items-start gap-3">
                    <Checkbox
                        id="notebook-delete-understand"
                        :model-value="acknowledged"
                        @update:model-value="acknowledged = $event === true"
                    />
                    <Label
                        for="notebook-delete-understand"
                        class="text-sm leading-snug font-normal"
                    >
                        I understand this cannot be undone.
                    </Label>
                </div>
            </template>

            <AlertDialogFooter>
                <AlertDialogCancel :id="CANCEL_ID"> Cancel </AlertDialogCancel>

                <Button
                    type="button"
                    variant="destructive"
                    :disabled="!canConfirm"
                    @click="confirmDelete"
                >
                    <LoaderCircleIcon
                        v-if="deleting"
                        class="animate-spin"
                        aria-hidden="true"
                    />
                    <Trash2Icon v-else aria-hidden="true" />
                    {{ deleting ? 'Deleting…' : 'Delete permanently' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
