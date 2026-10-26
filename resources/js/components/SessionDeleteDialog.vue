<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import {
    ArchiveIcon,
    LoaderCircleIcon,
    Trash2Icon,
    TriangleAlertIcon,
} from '@lucide/vue';
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
import { shortDate } from '@/lib/format';
import { browserTimezone } from '@/lib/library-filters';
import { archiveSession } from '@/lib/session-archive';
import {
    deleteSessionPermanently,
    fetchDeletePreview,
} from '@/lib/session-delete';
import type { ProgressNumbers, SessionDeletionPreview } from '@/types';

/**
 * The one confirmation every permanent delete goes through.
 *
 * It reads what the server would remove, so the warning always names the
 * session and shows exact counts. A finished session also shows only the
 * progress lines that would actually change, offers Archive as a reversible
 * alternative, and keeps Delete permanently disabled until the learner ticks
 * that they understand.
 *
 * If the session changed while the warning was open, the confirmation is
 * refused: the fresh numbers replace the old ones and the tick is cleared, so
 * nothing is deleted under numbers the learner has not seen.
 */
const props = defineProps<{
    /** Whether the warning is on screen. */
    open: boolean;
    /** The session being deleted. */
    sessionId: number;
    /** Used to name the session while the preview is still loading. */
    topic: string;
    /** Used to name the session while the preview is still loading. */
    sessionDate: string | null;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'deleted', session: { id: number; topic: string }): void;
    (event: 'archived', sessionId: number): void;
    (event: 'missing', sessionId: number): void;
}>();

const preview = ref<SessionDeletionPreview | null>(null);
const loading = ref(false);
const failed = ref(false);
const deleting = ref(false);
const archiving = ref(false);
const acknowledged = ref(false);
const staleMessage = ref('');

/** The id of the Cancel button, so the safe choice can take the first focus. */
const CANCEL_ID = 'session-delete-cancel';

/** Guards a slow preview for a warning that has since been closed. */
let generation = 0;

const shownTopic = computed(() => preview.value?.session.topic ?? props.topic);

const shownDate = computed(
    () => preview.value?.session.session_date ?? props.sessionDate,
);

const dayLabel = computed(() =>
    shownDate.value === null ? '' : shortDate(shownDate.value),
);

const isFinished = computed(() => preview.value?.session.finished === true);

const isArchived = computed(() => preview.value?.session.archived === true);

const countRows = computed(() => {
    const counts = preview.value?.counts;

    if (counts === undefined) {
        return [];
    }

    return [
        { label: 'Questions', value: counts.questions },
        { label: 'Words', value: counts.words },
        { label: 'Phrases', value: counts.phrases },
        { label: 'Mistakes', value: counts.mistakes },
        { label: 'Grammar notes', value: counts.grammar_notes },
    ];
});

interface ProgressChange {
    key: string;
    label: string;
    before: string;
    after: string;
}

function dayCount(value: number): string {
    return `${value} ${value === 1 ? 'day' : 'days'}`;
}

/** Only the lines that would change, with the exact numbers either side. */
const progressChanges = computed<ProgressChange[]>(() => {
    const progress = preview.value?.progress;

    if (progress === undefined) {
        return [];
    }

    const before: ProgressNumbers = progress.before;
    const after: ProgressNumbers = progress.after;

    const rows: ProgressChange[] = [
        {
            key: 'words',
            label: 'Words learned',
            before: String(before.words),
            after: String(after.words),
        },
        {
            key: 'phrases',
            label: 'Phrases saved',
            before: String(before.phrases),
            after: String(after.phrases),
        },
        {
            key: 'mistakes',
            label: 'Mistakes reviewed',
            before: String(before.mistakes),
            after: String(after.mistakes),
        },
        {
            key: 'grammar_notes',
            label: 'Grammar notes',
            before: String(before.grammar_notes),
            after: String(after.grammar_notes),
        },
        {
            key: 'practice_days',
            label: 'Practice days',
            before: String(before.practice_days),
            after: String(after.practice_days),
        },
        {
            key: 'current_streak',
            label: 'Current streak',
            before: dayCount(before.current_streak),
            after: dayCount(after.current_streak),
        },
    ];

    return rows.filter((row) => row.before !== row.after);
});

const canConfirm = computed(
    () =>
        preview.value !== null &&
        !deleting.value &&
        !archiving.value &&
        (!isFinished.value || acknowledged.value),
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
    archiving.value = false;
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

    const outcome = await fetchDeletePreview(
        props.sessionId,
        browserTimezone(),
    );

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
        emit('missing', props.sessionId);
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

async function archiveInstead(): Promise<void> {
    if (archiving.value || preview.value === null) {
        return;
    }

    archiving.value = true;

    const outcome = await archiveSession(props.sessionId);

    archiving.value = false;

    if (outcome === 'archived') {
        emit('archived', props.sessionId);
        emit('update:open', false);

        return;
    }

    if (outcome === 'unfinished') {
        toast.error('Finish this session to archive it.');

        return;
    }

    toast.error('We could not archive this session. Nothing was changed.');
}

async function confirmDelete(): Promise<void> {
    if (!canConfirm.value || preview.value === null) {
        return;
    }

    deleting.value = true;
    staleMessage.value = '';

    const outcome = await deleteSessionPermanently(
        props.sessionId,
        preview.value.token,
        browserTimezone(),
    );

    deleting.value = false;

    if (outcome.outcome === 'deleted') {
        emit('deleted', {
            id: outcome.result.session.id,
            topic: outcome.result.session.topic,
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
        emit('missing', props.sessionId);
        emit('update:open', false);

        return;
    }

    toast.error('We could not delete this session. Nothing was changed.');
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
            class="sm:max-w-lg!"
            @open-auto-focus="focusCancelFirst"
        >
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{
                        isFinished
                            ? 'Delete this finished session?'
                            : 'Delete this session?'
                    }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    <span class="font-medium text-foreground">
                        {{ shownTopic }}
                    </span>
                    <template v-if="dayLabel !== ''">
                        from {{ dayLabel }}</template
                    >
                    will be removed permanently.
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
                    <AlertTitle>We could not check this session</AlertTitle>
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
                    <AlertTitle>This session changed</AlertTitle>
                    <AlertDescription>
                        <p>{{ staleMessage }}</p>
                        <p class="mt-1">
                            The numbers below are fresh. Check them and confirm
                            again.
                        </p>
                    </AlertDescription>
                </Alert>

                <Alert v-if="isFinished" variant="destructive" class="mb-3">
                    <TriangleAlertIcon aria-hidden="true" />
                    <AlertTitle>This cannot be undone.</AlertTitle>
                    <AlertDescription>
                        <p>
                            This finished session will leave the Library,
                            Collections, and Review. Everything in it will be
                            removed for good.
                        </p>
                    </AlertDescription>
                </Alert>

                <p v-else class="mb-3 text-sm text-muted-foreground">
                    This cannot be undone. Because the session is unfinished,
                    your progress totals, practice days, and streak will not
                    change.
                </p>

                <section
                    aria-labelledby="delete-counts-title"
                    class="rounded-lg border p-3"
                >
                    <h3 id="delete-counts-title" class="text-sm font-medium">
                        What will be removed
                    </h3>

                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <div
                            v-for="row in countRows"
                            :key="row.label"
                            class="flex items-center justify-between gap-2"
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
                    v-if="isFinished"
                    aria-labelledby="delete-progress-title"
                    class="mt-3 rounded-lg border p-3"
                >
                    <h3 id="delete-progress-title" class="text-sm font-medium">
                        Effect on your progress
                    </h3>

                    <dl
                        v-if="progressChanges.length > 0"
                        class="mt-2 space-y-1 text-sm"
                    >
                        <div
                            v-for="change in progressChanges"
                            :key="change.key"
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">
                                {{ change.label }}
                            </dt>
                            <dd class="font-medium tabular-nums">
                                <span>{{ change.before }}</span>
                                <span aria-hidden="true"> → </span>
                                <span class="sr-only">to</span>
                                <span>{{ change.after }}</span>
                            </dd>
                        </div>
                    </dl>

                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Deleting this session will not change your totals,
                        practice days, or streak.
                    </p>
                </section>

                <div v-if="isFinished" class="mt-4 flex items-start gap-3">
                    <Checkbox
                        id="delete-understand"
                        :model-value="acknowledged"
                        @update:model-value="acknowledged = $event === true"
                    />
                    <Label
                        for="delete-understand"
                        class="text-sm leading-snug font-normal"
                    >
                        I understand this cannot be undone.
                    </Label>
                </div>
            </template>

            <AlertDialogFooter>
                <AlertDialogCancel :id="CANCEL_ID"> Cancel </AlertDialogCancel>

                <Button
                    v-if="isFinished && !isArchived && preview !== null"
                    type="button"
                    variant="outline"
                    :disabled="archiving || deleting"
                    @click="archiveInstead"
                >
                    <ArchiveIcon aria-hidden="true" />
                    {{ archiving ? 'Archiving…' : 'Archive instead' }}
                </Button>

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
