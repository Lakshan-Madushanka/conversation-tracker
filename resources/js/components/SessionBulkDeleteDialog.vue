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
import {
    archiveSessions,
    deleteSessionsPermanently,
    fetchBulkDeletePreview,
} from '@/lib/session-bulk';
import { archiveInsteadLabel, bulkDeleteTitle } from '@/lib/session-selection';
import type {
    BulkArchiveResult,
    BulkDeletionResult,
    ProgressNumbers,
    SessionDeletionBatchPreview,
} from '@/types';

/**
 * The one confirmation every permanent bulk delete goes through.
 *
 * It reads the combined effect of the selection, so the warning names the
 * sessions, shows exactly what leaves, and works out one set of progress
 * numbers rather than one per session. When a finished session is included it
 * warns prominently, offers Archive for those finished sessions, and keeps
 * Delete permanently disabled until the learner ticks that they understand.
 *
 * If the selection changed while the warning was open, the server refuses the
 * confirmation: the fresh numbers replace the old ones, the tick is cleared,
 * and nothing is deleted under numbers the learner has not seen.
 */
const props = defineProps<{
    /** Whether the warning is on screen. */
    open: boolean;
    /** The sessions the learner chose. */
    ids: number[];
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'deleted', result: BulkDeletionResult): void;
    (event: 'archived', result: BulkArchiveResult): void;
    (event: 'missing'): void;
}>();

const preview = ref<SessionDeletionBatchPreview | null>(null);
const loading = ref(false);
const failed = ref(false);
const deleting = ref(false);
const archiving = ref(false);
const acknowledged = ref(false);
const staleMessage = ref('');

/** The id of the Cancel button, so the safe choice can take the first focus. */
const CANCEL_ID = 'bulk-delete-cancel';

/** Guards a slow preview for a warning that has since been closed. */
let generation = 0;

const shownCount = computed(
    () => preview.value?.session_count ?? props.ids.length,
);

const title = computed(() => bulkDeleteTitle(shownCount.value));

const hasFinished = computed(() => (preview.value?.finished_count ?? 0) > 0);

const finishedCount = computed(() => preview.value?.finished_count ?? 0);

/** Deleting the whole selection is only possible once the numbers are known. */
const canConfirm = computed(
    () =>
        preview.value !== null &&
        !deleting.value &&
        !archiving.value &&
        (!hasFinished.value || acknowledged.value),
);

/** Archive can only help when at least one finished session is still in the Library. */
const canArchiveInstead = computed(
    () =>
        preview.value !== null &&
        preview.value.archivable_count > 0 &&
        !deleting.value &&
        !archiving.value,
);

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

/** Only the progress lines that would change, with the exact numbers either side. */
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

/** The first few sessions, named the way the cards name them. */
const namedSessions = computed(() =>
    (preview.value?.names ?? []).map((name) => ({
        topic: name.topic,
        day: name.session_date === null ? '' : shortDate(name.session_date),
    })),
);

const moreCount = computed(() => preview.value?.more_count ?? 0);

function close(): void {
    if (deleting.value || archiving.value) {
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

    const result = await fetchBulkDeletePreview(props.ids, browserTimezone());

    if (run !== generation || !props.open) {
        return;
    }

    loading.value = false;

    if (result === null) {
        failed.value = true;
        await focusCancel();

        return;
    }

    // The sessions left while the warning was opening, so there is nothing
    // left to confirm. The page reports it calmly and refreshes its list.
    if (result.session_count === 0) {
        emit('missing');
        emit('update:open', false);

        return;
    }

    preview.value = result;
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
    if (!canArchiveInstead.value) {
        return;
    }

    archiving.value = true;

    const result = await archiveSessions(props.ids);

    archiving.value = false;

    if (result === null) {
        toast.error(
            'We could not archive these sessions. Nothing was changed.',
        );

        return;
    }

    emit('archived', result);
    emit('update:open', false);
}

async function confirmDelete(): Promise<void> {
    if (!canConfirm.value || preview.value === null) {
        return;
    }

    deleting.value = true;
    staleMessage.value = '';

    const outcome = await deleteSessionsPermanently(
        props.ids,
        preview.value.token,
        browserTimezone(),
    );

    deleting.value = false;

    if (outcome.outcome === 'deleted') {
        emit('deleted', outcome.result);
        emit('update:open', false);

        return;
    }

    if (outcome.outcome === 'stale') {
        // Everything left while the confirmation was on its way.
        if (outcome.preview.session_count === 0) {
            emit('missing');
            emit('update:open', false);

            return;
        }

        preview.value = outcome.preview;
        acknowledged.value = false;
        staleMessage.value = outcome.message;
        await focusCancel();

        return;
    }

    toast.error('We could not delete these sessions. Nothing was changed.');
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
            class="flex max-h-[85dvh] w-full max-w-[calc(100vw-2rem)]! flex-col gap-0 overflow-hidden p-0 sm:max-w-xl!"
            @open-auto-focus="focusCancelFirst"
        >
            <AlertDialogHeader class="shrink-0 px-4 pt-4 pb-3">
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="preview !== null">
                        {{ preview.session_count }}
                        {{
                            preview.session_count === 1 ? 'session' : 'sessions'
                        }}
                        will be removed permanently.
                    </template>
                    <template v-else>
                        These sessions will be removed permanently.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div
                class="min-h-0 flex-1 overflow-y-auto px-4 pb-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:ring-inset"
                tabindex="0"
            >
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
                        <AlertTitle
                            >We could not check these sessions</AlertTitle
                        >
                        <AlertDescription>
                            <p>
                                Nothing has been deleted. Check your connection
                                and try again.
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
                        <AlertTitle>These sessions changed</AlertTitle>
                        <AlertDescription>
                            <p>{{ staleMessage }}</p>
                            <p class="mt-1">
                                The numbers below are fresh. Check them and
                                confirm again.
                            </p>
                        </AlertDescription>
                    </Alert>

                    <Alert
                        v-if="hasFinished"
                        variant="destructive"
                        class="mb-3"
                    >
                        <TriangleAlertIcon aria-hidden="true" />
                        <AlertTitle>This cannot be undone.</AlertTitle>
                        <AlertDescription>
                            <p>
                                {{ finishedCount }}
                                {{
                                    finishedCount === 1
                                        ? 'finished session'
                                        : 'finished sessions'
                                }}
                                will leave the Library, Collections, and Review.
                                Everything in them will be removed for good.
                            </p>
                        </AlertDescription>
                    </Alert>

                    <p v-else class="mb-3 text-sm text-muted-foreground">
                        These sessions are unfinished, so your progress totals,
                        practice days, and streak will not change.
                    </p>

                    <section
                        aria-labelledby="bulk-delete-names-title"
                        class="rounded-lg border p-3"
                    >
                        <h3
                            id="bulk-delete-names-title"
                            class="text-sm font-medium"
                        >
                            What will be removed
                        </h3>

                        <ul class="mt-2 space-y-1 text-sm">
                            <li
                                v-for="(named, index) in namedSessions"
                                :key="`${named.topic}-${index}`"
                                class="break-words"
                            >
                                <span class="font-medium text-foreground">
                                    {{ named.topic }}
                                </span>
                                <template v-if="named.day !== ''">
                                    from {{ named.day }}</template
                                >
                            </li>
                        </ul>

                        <p v-if="moreCount > 0" class="mt-1 text-sm">
                            and {{ moreCount }} more
                        </p>

                        <p
                            v-if="preview.missing_count > 0"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            {{ preview.missing_count }}
                            {{
                                preview.missing_count === 1
                                    ? 'session was'
                                    : 'sessions were'
                            }}
                            already deleted and will be skipped.
                        </p>

                        <dl
                            class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 border-t pt-3 text-sm"
                        >
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
                        v-if="hasFinished"
                        aria-labelledby="bulk-delete-progress-title"
                        class="mt-3 rounded-lg border p-3"
                    >
                        <h3
                            id="bulk-delete-progress-title"
                            class="text-sm font-medium"
                        >
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
                            Deleting these sessions will not change your totals,
                            practice days, or streak.
                        </p>
                    </section>

                    <div v-if="hasFinished" class="mt-4 flex items-start gap-3">
                        <Checkbox
                            id="bulk-delete-understand"
                            :model-value="acknowledged"
                            @update:model-value="acknowledged = $event === true"
                        />
                        <Label
                            for="bulk-delete-understand"
                            class="text-sm leading-snug font-normal"
                        >
                            I understand this cannot be undone.
                        </Label>
                    </div>
                </template>
            </div>

            <AlertDialogFooter class="mx-0 mb-0 shrink-0 bg-muted sm:flex-wrap">
                <AlertDialogCancel :id="CANCEL_ID"> Cancel </AlertDialogCancel>

                <Button
                    v-if="canArchiveInstead"
                    type="button"
                    variant="outline"
                    class="h-auto py-1.5 whitespace-normal"
                    :disabled="archiving || deleting"
                    @click="archiveInstead"
                >
                    <ArchiveIcon aria-hidden="true" />
                    {{
                        archiving
                            ? 'Archiving…'
                            : archiveInsteadLabel(
                                  preview?.archivable_count ?? 0,
                              )
                    }}
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
