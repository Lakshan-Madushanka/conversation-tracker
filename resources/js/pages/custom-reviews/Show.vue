<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    ArrowUpRightIcon,
    ChevronDownIcon,
    ListChecksIcon,
    PlayIcon,
    PlusIcon,
    Trash2Icon,
    TriangleAlertIcon,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import CustomReviewAttemptItems from '@/components/CustomReviewAttemptItems.vue';
import CustomReviewDeleteDialog from '@/components/CustomReviewDeleteDialog.vue';
import CustomReviewStatusControl from '@/components/CustomReviewStatusControl.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SaveIndicator from '@/components/SaveIndicator.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useAutoSave } from '@/composables/useAutoSave';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ARCHIVED_ITEM_NOTE,
    clearReview,
    createReviewDescriptionSaver,
    createReviewNameSaver,
    NOT_AVAILABLE_LABEL,
    removeItemsFromReview,
    REVIEW_EMPTY_REASON,
    REVIEW_EMPTIED_NOTE,
    reviewIsTooMany,
    reviewItemCountLabel,
    reviewItemName,
    reviewStatusExplanation,
    reviewStatusLabel,
    reviewTooManyLabel,
    sendAddToReview,
    updateReviewStatus,
} from '@/lib/custom-review';
import { shortDate } from '@/lib/format';
import type {
    CustomReviewAttemptHistoryData,
    CustomReviewGroupData,
    CustomReviewItemData,
    CustomReviewKind,
    CustomReviewPageData,
    CustomReviewSummary,
} from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<CustomReviewPageData>();

/**
 * The review as the page knows it now. Autosaves and status moves answer with
 * the review as the server saved it, so the header, the tag and the control
 * all follow one record.
 */
const review = ref<CustomReviewSummary>({ ...props.review });

/** The name and description the learner is editing. */
const name = ref(props.review.name);
const reviewDescription = ref(props.review.description ?? '');

const nameSaver = useAutoSave({
    snapshot: () => ({ name: name.value }),
    save: createReviewNameSaver(props.review.id),
    hasTopic: () => true,
    hasContent: () => true,
    sessionId: props.review.id,
    version: 0,
    onSaved: (saved) => {
        review.value = { ...saved.review };
    },
});

const descriptionSaver = useAutoSave({
    snapshot: () => ({ description: reviewDescription.value }),
    save: createReviewDescriptionSaver(props.review.id),
    hasTopic: () => true,
    hasContent: () => true,
    sessionId: props.review.id,
    version: 0,
    onSaved: (saved) => {
        review.value = { ...saved.review };
    },
});

/** What to say when a field could not be saved, in the server's words. */
const nameNotice = computed(() => {
    if (nameSaver.status.value !== 'failed') {
        return '';
    }

    return (
        nameSaver.errors.value.name ??
        'Your name was not saved. Check your connection and try again.'
    );
});

const descriptionNotice = computed(() => {
    if (descriptionSaver.status.value !== 'failed') {
        return '';
    }

    return (
        descriptionSaver.errors.value.description ??
        'Your description was not saved. Check your connection and try again.'
    );
});

/** True while the learner's Status move is on its way to the server. */
const statusBusy = ref(false);
const statusError = ref('');

/** True while Start practice is on its way to the server. */
const startBusy = ref(false);

function startPractice(): void {
    if (props.practice.start_url === null || startBusy.value) {
        return;
    }

    startBusy.value = true;
    router.post(
        props.practice.start_url,
        {},
        {
            onFinish: () => {
                startBusy.value = false;
            },
        },
    );
}

/** Which attempt's items are open; the address keeps it across a refresh. */
const openAttempt = ref<number | null>(props.expanded_attempt);

watch(
    () => props.expanded_attempt,
    (value) => {
        openAttempt.value = value;
    },
);

function toggleAttempt(
    attempt: CustomReviewAttemptHistoryData,
    open: boolean,
): void {
    openAttempt.value = open ? attempt.id : null;

    const url = new URL(window.location.href);

    if (open) {
        url.searchParams.set('attempt', String(attempt.id));
    } else {
        url.searchParams.delete('attempt');
    }

    window.history.replaceState(window.history.state, '', url);
}

/** Every item the attempt kept, however it ended. */
function attemptTotal(attempt: CustomReviewAttemptHistoryData): number {
    return (
        attempt.counts.rated + attempt.counts.skipped + attempt.counts.unrated
    );
}

function attemptStarted(attempt: CustomReviewAttemptHistoryData): string {
    return attempt.started_at === null ? '—' : shortDate(attempt.started_at);
}

function attemptFinished(attempt: CustomReviewAttemptHistoryData): string {
    return attempt.finished_at === null ? '—' : shortDate(attempt.finished_at);
}

function attemptStatusVariant(
    attempt: CustomReviewAttemptHistoryData,
): 'default' | 'secondary' {
    return attempt.status === 'in_progress' ? 'default' : 'secondary';
}

function attemptCounts(attempt: CustomReviewAttemptHistoryData): string {
    const total = attemptTotal(attempt);
    const parts = [`${attempt.counts.rated} of ${total} rated`];

    if (attempt.counts.skipped > 0) {
        parts.push(`${attempt.counts.skipped} skipped`);
    }

    return parts.join(' · ');
}

/** The items on screen, read live from their sessions by the server. */
const groups = ref<CustomReviewGroupData[]>(cloneGroups(props.groups));

/** True while a remove or an undo is on its way, so none can overlap. */
const busy = ref(false);

const selectedIds = ref<Set<string>>(new Set());

const clearOpen = ref(false);

/** Whether the warning that takes the whole review for good is on screen. */
const deleteOpen = ref(false);

const items = computed(() => groups.value.flatMap((group) => group.items));

const count = computed(() => items.value.length);

/**
 * Whether this review has held an item as far as the page can tell: it holds
 * some now, lost its last one during this visit, or has an attempt history
 * that could only have been made from items. Losing an item to a deletion or
 * an archive is not the same as never having had one, and the page says so.
 */
const everHeldItems = ref(
    props.review.item_count > 0 || props.attempts.length > 0,
);

watch(count, (value) => {
    if (value > 0) {
        everHeldItems.value = true;
    }
});

/** The size tag follows the items on screen, so a removal clears it at once. */
const tooMany = computed(() => reviewIsTooMany(count.value));

const tooManyLabel = computed(() => reviewTooManyLabel(count.value));

/**
 * Items the learner can still fix: an archived item is not waiting for
 * anything, so it carries its own marker instead of joining this list.
 */
const notEligible = computed(() =>
    items.value.filter((item) => !item.eligible && item.available),
);

const notEligibleLabel = computed(() =>
    notEligible.value.length === 1
        ? 'This item is not ready to practise yet:'
        : `These ${notEligible.value.length} items are not ready to practise yet:`,
);

const selectedItems = computed(() =>
    items.value.filter((item) => selectedIds.value.has(item.id)),
);

const selectedCount = computed(() => selectedItems.value.length);

const selectedLabel = computed(() =>
    selectedCount.value === 1
        ? '1 item selected'
        : `${selectedCount.value} items selected`,
);

/** The header line: how big the review is, and what state it is in. */
const description = computed(() =>
    count.value === 0
        ? 'Nothing is in this review yet. Choose items in Collections and use Add to review.'
        : `${reviewItemCountLabel(count.value)} · ${review.value.status_label}`,
);

/** What the current status means, said under the control. */
const statusExplanation = computed(() =>
    reviewStatusExplanation(review.value.status),
);

/**
 * The learner's own move. The screen shows the choice at once, and puts the
 * previous status back with the server's words if it cannot be made.
 */
async function changeStatus(next: 'draft' | 'finished'): Promise<void> {
    if (statusBusy.value || review.value.status === next || count.value === 0) {
        return;
    }

    const previous = review.value;

    review.value = {
        ...previous,
        status: next,
        status_label: reviewStatusLabel(next),
    };
    statusBusy.value = true;
    statusError.value = '';

    try {
        review.value = { ...(await updateReviewStatus(props.review.id, next)) };
    } catch (error) {
        review.value = previous;
        statusError.value =
            error instanceof Error
                ? error.message
                : 'This review could not be moved just now.';
    } finally {
        statusBusy.value = false;
    }
}

function cloneGroups(source: CustomReviewGroupData[]): CustomReviewGroupData[] {
    return source.map((group) => ({
        ...group,
        items: group.items.map((item) => ({ ...item })),
    }));
}

/** The server's freshest list, after a trip to another tab or a save. */
watch(
    () => props.groups,
    (next) => {
        groups.value = cloneGroups(next);
        pruneSelection();
    },
);

/** The server's review after a removal, an add or a trip to a session. */
watch(
    () => props.review,
    (next) => {
        review.value = { ...next };
    },
);

function pruneSelection(): void {
    const present = new Set(items.value.map((item) => item.id));
    const next = new Set(
        [...selectedIds.value].filter((id) => present.has(id)),
    );

    if (next.size !== selectedIds.value.size) {
        selectedIds.value = next;
    }
}

function isSelected(item: CustomReviewItemData): boolean {
    return selectedIds.value.has(item.id);
}

function toggleItem(item: CustomReviewItemData): void {
    const next = new Set(selectedIds.value);

    if (next.has(item.id)) {
        next.delete(item.id);
    } else {
        next.add(item.id);
    }

    selectedIds.value = next;
}

/** What one kind of item is called in words, for accessible names. */
function unitFor(kind: CustomReviewKind): string {
    const units: Record<CustomReviewKind, string> = {
        vocabulary: 'word',
        phrase: 'phrase',
        grammar_note: 'grammar note',
        mistake: 'mistake',
        question: 'question',
    };

    return units[kind];
}

function nameFor(item: CustomReviewItemData): string {
    return reviewItemName(item.kind, item.title);
}

function selectLabel(item: CustomReviewItemData): string {
    return `Select the ${unitFor(item.kind)} ${nameFor(item)}`;
}

function removeLabel(item: CustomReviewItemData): string {
    return `Remove the ${unitFor(item.kind)} ${nameFor(item)} from this review`;
}

function checkboxId(item: CustomReviewItemData): string {
    return `review-select-${encodeURIComponent(item.id)}`;
}

function sourceLabel(item: CustomReviewItemData): string {
    const date = item.session_date === null ? '' : shortDate(item.session_date);

    return [item.topic, date].filter((piece) => piece !== '').join(' · ');
}

function groupByKind(
    itemsToGroup: CustomReviewItemData[],
): Map<CustomReviewKind, CustomReviewItemData[]> {
    const grouped = new Map<CustomReviewKind, CustomReviewItemData[]>();

    for (const item of itemsToGroup) {
        const list = grouped.get(item.kind) ?? [];
        list.push(item);
        grouped.set(item.kind, list);
    }

    return grouped;
}

/** Takes the gone items off the screen and out of the selection at once. */
function applyRemoved(kind: CustomReviewKind, identities: Set<string>): void {
    groups.value = groups.value
        .map((group) => {
            if (group.key !== kind) {
                return group;
            }

            const remaining = group.items.filter(
                (item) => !identities.has(item.identity),
            );

            return { ...group, items: remaining, count: remaining.length };
        })
        .filter((group) => group.items.length > 0);

    const goneIds = new Set(
        items.value
            .filter(
                (item) => item.kind === kind && identities.has(item.identity),
            )
            .map((item) => item.id),
    );

    selectedIds.value = new Set(
        [...selectedIds.value].filter((id) => !goneIds.has(id)),
    );
}

/**
 * The header's status word follows what the review holds, which a removal may
 * have just changed, so the header is read again rather than restated here.
 */
function refreshHeader(): void {
    router.reload({ only: ['review'] });
}

/**
 * Takes the chosen items out, one kind at a time, and returns what left.
 *
 * Each kind's answer is applied as it arrives, so a failure midway leaves the
 * screen telling the truth about what is still there.
 */
async function removeMany(
    itemsToRemove: CustomReviewItemData[],
): Promise<CustomReviewItemData[]> {
    if (busy.value || itemsToRemove.length === 0) {
        return [];
    }

    const removed: CustomReviewItemData[] = [];

    busy.value = true;

    try {
        for (const [kind, kindItems] of groupByKind(itemsToRemove)) {
            const identities = kindItems.map((item) => item.identity);
            const result = await removeItemsFromReview(
                props.review.id,
                kind,
                identities,
            );

            // An answer that removed nothing means the review no longer held
            // those rows, so the screen lets them go as well.
            const gone =
                result.removed_count === 0
                    ? new Set(identities)
                    : new Set(result.removed_identities);

            applyRemoved(kind, gone);

            removed.push(
                ...kindItems.filter((item) => gone.has(item.identity)),
            );
        }

        if (removed.length > 0) {
            refreshHeader();
        }

        return removed;
    } catch {
        toast.error(
            'Some items were not removed. Check your connection and try again.',
        );

        return removed;
    } finally {
        busy.value = false;
    }
}

/** What leaving a review sounds like, said once for the whole batch. */
function removedMessage(removed: CustomReviewItemData[]): string {
    if (removed.length === 1) {
        return `Removed “${nameFor(removed[0])}” from this review.`;
    }

    return `Removed ${removed.length} items from this review.`;
}

/**
 * Puts the items back, in one go and without asking again.
 *
 * The review is named by the address, and the add is confirmed on the way, so
 * Undo of a removal can never answer with the size warning: the learner has
 * already said they wanted these items.
 */
async function undoRemoval(removed: CustomReviewItemData[]): Promise<void> {
    if (busy.value || removed.length === 0) {
        return;
    }

    busy.value = true;

    try {
        for (const [kind, kindItems] of groupByKind(removed)) {
            const answer = await sendAddToReview(
                kind,
                kindItems.map((item) => item.identity),
                { review: props.review.id, confirmed: true },
            );

            if (answer.outcome !== 'added') {
                throw new Error('The review did not take the items back.');
            }
        }

        toast.success(
            removed.length === 1
                ? 'Put back in this review.'
                : `Put ${removed.length} items back in this review.`,
        );
    } catch {
        toast.error(
            'Could not undo that. Check your connection and try again.',
        );
    } finally {
        busy.value = false;
    }

    // The server's order is the one the page keeps, so the list is read again
    // rather than guessed at from what was on screen.
    router.reload({ only: ['groups', 'review'] });
}

/** One row's Remove acts at once, with a few seconds of Undo. */
async function removeOne(item: CustomReviewItemData): Promise<void> {
    const removed = await removeMany([item]);

    if (removed.length === 0) {
        return;
    }

    toast.success(removedMessage(removed), {
        duration: 7000,
        action: {
            label: 'Undo',
            onClick: () => {
                void undoRemoval(removed);
            },
        },
    });
}

/** Remove every chosen row, then offer one Undo for all of them. */
async function removeSelected(): Promise<void> {
    if (selectedCount.value === 0) {
        return;
    }

    const removed = await removeMany(selectedItems.value);

    if (removed.length === 0) {
        return;
    }

    toast.success(removedMessage(removed), {
        duration: 7000,
        action: {
            label: 'Undo',
            onClick: () => {
                void undoRemoval(removed);
            },
        },
    });
}

/** Empty the review after the warning. There is no way back from this. */
async function clearAll(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        const cleared = await clearReview(props.review.id);

        groups.value = [];
        selectedIds.value = new Set();
        clearOpen.value = false;
        refreshHeader();
        toast.success(
            cleared === 1
                ? 'The review is empty again.'
                : `Took ${cleared} items out. The review is empty again.`,
        );
    } catch {
        toast.error(
            'The review was not cleared. Nothing changed — check your connection and try again.',
        );
    } finally {
        busy.value = false;
    }
}

/**
 * Coming back from an original session in another tab can leave the list out
 * of date: an item may have been edited, archived or deleted there. The page
 * asks for the list again, unless a removal is already under way.
 */
let hiddenAt = 0;

function onVisibilityChange(): void {
    if (document.visibilityState === 'hidden') {
        hiddenAt = Date.now();

        return;
    }

    if (busy.value || Date.now() - hiddenAt < 1000) {
        return;
    }

    router.reload({ only: ['groups', 'review'] });
}

onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <div>
        <Head :title="review.name" />

        <div class="mb-4">
            <Button variant="ghost" size="sm" as-child>
                <Link :href="props.back_url">
                    <ArrowLeftIcon aria-hidden="true" />
                    Back
                </Link>
            </Button>
        </div>

        <PageHeader :title="review.name" :description="description">
            <template #actions>
                <Button size="sm" class="min-h-11 md:min-h-9" as-child>
                    <Link :href="props.add_items_url">
                        <PlusIcon aria-hidden="true" />
                        Add items
                    </Link>
                </Button>

                <Button
                    v-if="count > 0"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="min-h-11 md:min-h-9"
                    :disabled="busy"
                    @click="clearOpen = true"
                >
                    <Trash2Icon aria-hidden="true" />
                    Clear review
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="min-h-11 text-muted-foreground hover:text-destructive md:min-h-9"
                    @click="deleteOpen = true"
                >
                    <Trash2Icon aria-hidden="true" />
                    Delete review
                    <span class="sr-only"> {{ review.name }}</span>
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="props.attempt_error" class="mb-6" role="alert">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Practice could not start</AlertTitle>
            <AlertDescription>{{ props.attempt_error }}</AlertDescription>
        </Alert>

        <section aria-labelledby="practice-heading" class="mb-8">
            <Card class="py-0">
                <div
                    class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6"
                >
                    <div class="grid gap-1">
                        <h2
                            id="practice-heading"
                            class="text-base font-semibold"
                        >
                            Practice
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="props.practice.in_progress">
                                You have an attempt in progress. Pick up where
                                you left off — nothing is lost.
                            </template>
                            <template v-else-if="props.practice.can_start">
                                Ready when you are. An attempt holds every item
                                that is available right now.
                            </template>
                            <template v-else>
                                {{
                                    props.practice.note ??
                                    'This review is not ready to practise yet.'
                                }}
                            </template>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="
                                props.practice.in_progress &&
                                props.practice.resume_url
                            "
                            size="lg"
                            class="min-h-11 md:min-h-9"
                            as-child
                        >
                            <Link :href="props.practice.resume_url">
                                <PlayIcon aria-hidden="true" />
                                Resume attempt
                            </Link>
                        </Button>

                        <Button
                            v-else-if="
                                props.practice.can_start &&
                                props.practice.start_url
                            "
                            type="button"
                            size="lg"
                            class="min-h-11 md:min-h-9"
                            :disabled="startBusy"
                            @click="startPractice"
                        >
                            <PlayIcon aria-hidden="true" />
                            {{ props.practice.start_label }}
                        </Button>
                    </div>
                </div>
            </Card>
        </section>

        <section aria-labelledby="review-details-heading" class="mb-8">
            <h2 id="review-details-heading" class="sr-only">
                Name, description and status
            </h2>

            <Card class="py-0">
                <div class="grid gap-6 p-4 sm:p-6">
                    <div class="grid gap-1.5">
                        <div
                            class="flex min-h-5 flex-wrap items-center justify-between gap-2"
                        >
                            <Label for="review-name">Name</Label>
                            <SaveIndicator
                                :status="nameSaver.status.value"
                                :last-saved-at="nameSaver.lastSavedAt.value"
                            />
                        </div>

                        <Input
                            id="review-name"
                            v-model="name"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            :maxlength="80"
                            :aria-invalid="nameNotice !== ''"
                            aria-describedby="review-name-help review-name-notice"
                        />

                        <p
                            id="review-name-help"
                            class="text-xs text-muted-foreground"
                        >
                            Keep it short. Up to 80 characters.
                        </p>

                        <p
                            v-if="nameNotice !== ''"
                            id="review-name-notice"
                            class="text-xs font-medium text-destructive"
                            role="alert"
                        >
                            {{ nameNotice }}
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                class="h-auto p-0"
                                @click="nameSaver.retry()"
                            >
                                Try again
                            </Button>
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <div
                            class="flex min-h-5 flex-wrap items-center justify-between gap-2"
                        >
                            <Label for="review-description">
                                Description
                                <span class="text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <SaveIndicator
                                :status="descriptionSaver.status.value"
                                :last-saved-at="
                                    descriptionSaver.lastSavedAt.value
                                "
                            />
                        </div>

                        <Textarea
                            id="review-description"
                            v-model="reviewDescription"
                            rows="3"
                            :maxlength="500"
                            :aria-invalid="descriptionNotice !== ''"
                            aria-describedby="review-description-help review-description-notice"
                        />

                        <p
                            id="review-description-help"
                            class="text-xs text-muted-foreground"
                        >
                            What this review is for. Up to 500 characters.
                        </p>

                        <p
                            v-if="descriptionNotice !== ''"
                            id="review-description-notice"
                            class="text-xs font-medium text-destructive"
                            role="alert"
                        >
                            {{ descriptionNotice }}
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                class="h-auto p-0"
                                @click="descriptionSaver.retry()"
                            >
                                Try again
                            </Button>
                        </p>
                    </div>

                    <div class="grid gap-2 border-t pt-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium">Status</span>
                            <Badge variant="secondary">
                                {{ review.status_label }}
                            </Badge>
                        </div>

                        <CustomReviewStatusControl
                            :status="review.status"
                            :can-finish="count > 0"
                            :busy="statusBusy"
                            @change="changeStatus"
                        />

                        <p class="text-xs text-muted-foreground">
                            {{ statusExplanation }}
                        </p>

                        <p
                            v-if="review.status === 'empty'"
                            class="text-xs text-muted-foreground"
                        >
                            {{ REVIEW_EMPTY_REASON }}
                        </p>

                        <p
                            v-if="statusError !== ''"
                            class="text-xs font-medium text-destructive"
                            role="alert"
                        >
                            {{ statusError }}
                        </p>
                    </div>
                </div>
            </Card>
        </section>

        <div v-if="tooMany" class="mb-6 flex flex-wrap items-center gap-2">
            <Badge variant="outline">
                {{ tooManyLabel }}
            </Badge>
            <p class="text-xs text-muted-foreground">
                Long reviews take a while to practise. This is advice — you can
                keep every item.
            </p>
        </div>

        <Alert v-if="notEligible.length > 0" class="mb-6">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Not eligible yet</AlertTitle>
            <AlertDescription>
                <p>
                    {{ notEligibleLabel }} They stay in this review, and you can
                    come back to them when they are ready.
                </p>

                <ul class="mt-2 grid list-disc gap-1 pl-5">
                    <li
                        v-for="item in notEligible"
                        :key="item.id"
                        class="text-sm"
                    >
                        <span class="font-medium">{{ nameFor(item) }}</span>
                        <span aria-hidden="true"> — </span>
                        <span class="sr-only">: </span>
                        {{ item.reason }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <EmptyState
            v-if="count === 0"
            :icon="ListChecksIcon"
            :title="
                everHeldItems
                    ? 'This review is empty now'
                    : 'Nothing in this review yet'
            "
            :description="
                everHeldItems
                    ? REVIEW_EMPTIED_NOTE
                    : 'Choose words, phrases, notes, mistakes or questions that are ready to practise. They will wait here until you are ready.'
            "
        >
            <template #actions>
                <Button size="lg" as-child>
                    <Link :href="props.add_items_url"> Add items </Link>
                </Button>
            </template>
        </EmptyState>

        <div v-else class="space-y-8">
            <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-muted/40 p-3"
            >
                <p class="text-sm text-muted-foreground">
                    {{
                        selectedCount === 0
                            ? 'Choose the items you want to remove.'
                            : selectedLabel
                    }}
                </p>

                <Button
                    v-if="selectedCount > 0"
                    type="button"
                    variant="destructive"
                    size="sm"
                    class="min-h-11 md:min-h-9"
                    :disabled="busy"
                    @click="removeSelected"
                >
                    <Trash2Icon aria-hidden="true" />
                    Remove selected
                </Button>
            </div>

            <section
                v-for="group in groups"
                :key="group.key"
                :aria-labelledby="`review-group-${group.key}`"
            >
                <h2
                    :id="`review-group-${group.key}`"
                    class="flex items-baseline gap-2 text-sm font-semibold"
                >
                    {{ group.label }}
                    <span class="text-xs font-normal text-muted-foreground">
                        {{ group.count }}
                    </span>
                </h2>

                <ul class="mt-3 grid gap-2">
                    <li v-for="item in group.items" :key="item.id">
                        <Card class="py-0">
                            <div class="flex items-start gap-3 p-4">
                                <span class="pt-1" @click.stop>
                                    <Checkbox
                                        :id="checkboxId(item)"
                                        :model-value="isSelected(item)"
                                        :aria-label="selectLabel(item)"
                                        @update:model-value="toggleItem(item)"
                                    />
                                </span>

                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <p class="text-sm font-medium break-words">
                                        <span
                                            v-if="item.kind === 'mistake'"
                                            class="sr-only"
                                        >
                                            What I said:
                                        </span>
                                        {{ item.title }}
                                    </p>

                                    <p
                                        v-if="item.kind === 'mistake'"
                                        class="text-sm break-words"
                                    >
                                        <span aria-hidden="true">→ </span>
                                        <span class="sr-only">
                                            Correction:
                                        </span>
                                        <span
                                            v-if="item.correction_html"
                                            v-html="item.correction_html"
                                        ></span>
                                        <span v-else-if="item.correction">
                                            {{ item.correction }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground italic"
                                        >
                                            No correction yet
                                        </span>
                                    </p>

                                    <p class="text-xs text-muted-foreground">
                                        {{ sourceLabel(item) }}
                                    </p>

                                    <div
                                        v-if="!item.eligible && item.available"
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <Badge variant="secondary">
                                            Needs attention
                                        </Badge>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ item.reason }}
                                        </span>
                                    </div>

                                    <div
                                        v-else-if="!item.available"
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <Badge variant="outline">
                                            {{ NOT_AVAILABLE_LABEL }}
                                        </Badge>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ ARCHIVED_ITEM_NOTE }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex flex-wrap items-center gap-2 pt-1"
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="min-h-9"
                                            as-child
                                        >
                                            <a
                                                :href="item.href"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <ArrowUpRightIcon
                                                    aria-hidden="true"
                                                />
                                                See original
                                                <span class="sr-only">
                                                    for the
                                                    {{ unitFor(item.kind) }}
                                                    {{ nameFor(item) }} (opens
                                                    in a new tab)
                                                </span>
                                            </a>
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="min-h-9"
                                            :disabled="busy"
                                            :aria-label="removeLabel(item)"
                                            @click="removeOne(item)"
                                        >
                                            <Trash2Icon aria-hidden="true" />
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </Card>
                    </li>
                </ul>
            </section>
        </div>

        <section
            id="attempts"
            aria-labelledby="attempt-history-heading"
            class="mt-10 border-t pt-6"
        >
            <h2 id="attempt-history-heading" class="text-base font-semibold">
                Attempt history
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Every attempt keeps the words it started with. Reading one
                changes nothing.
            </p>

            <p
                v-if="props.attempts.length === 0"
                class="mt-4 text-sm text-muted-foreground"
            >
                No attempts yet. Start practice and the first one will appear
                here.
            </p>

            <template v-else>
                <!-- Wide screens read the history as a table. -->
                <div class="mt-4 hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Attempt</TableHead>
                                <TableHead>Started</TableHead>
                                <TableHead>Finished</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Rated</TableHead>
                                <TableHead class="text-right">
                                    Items
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template
                                v-for="attempt in props.attempts"
                                :key="attempt.id"
                            >
                                <TableRow>
                                    <TableCell class="font-medium">
                                        {{ attempt.number_label }}
                                    </TableCell>
                                    <TableCell>
                                        {{ attemptStarted(attempt) }}
                                    </TableCell>
                                    <TableCell>
                                        {{ attemptFinished(attempt) }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                attemptStatusVariant(attempt)
                                            "
                                        >
                                            {{ attempt.status_label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {{ attemptCounts(attempt) }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="min-h-9"
                                            :aria-expanded="
                                                openAttempt === attempt.id
                                            "
                                            @click="
                                                toggleAttempt(
                                                    attempt,
                                                    openAttempt !== attempt.id,
                                                )
                                            "
                                        >
                                            {{
                                                openAttempt === attempt.id
                                                    ? 'Hide items'
                                                    : 'Show items'
                                            }}
                                            <ChevronDownIcon
                                                class="transition-transform"
                                                :class="
                                                    openAttempt === attempt.id
                                                        ? 'rotate-180'
                                                        : ''
                                                "
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="openAttempt === attempt.id">
                                    <TableCell
                                        :colspan="6"
                                        class="bg-muted/30 whitespace-normal"
                                    >
                                        <CustomReviewAttemptItems
                                            :attempt="attempt"
                                        />
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>

                <!-- Phones read the same history as stacked cards. -->
                <ul class="mt-4 grid gap-2 md:hidden">
                    <li v-for="attempt in props.attempts" :key="attempt.id">
                        <Card class="py-0">
                            <Collapsible
                                :open="openAttempt === attempt.id"
                                @update:open="
                                    (open) => toggleAttempt(attempt, open)
                                "
                            >
                                <div class="grid gap-2 p-4">
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p class="font-medium">
                                            {{ attempt.number_label }}
                                        </p>
                                        <Badge
                                            :variant="
                                                attemptStatusVariant(attempt)
                                            "
                                        >
                                            {{ attempt.status_label }}
                                        </Badge>
                                    </div>

                                    <p class="text-sm text-muted-foreground">
                                        Started
                                        {{ attemptStarted(attempt) }}
                                        <span aria-hidden="true">·</span>
                                        Finished
                                        {{ attemptFinished(attempt) }}
                                    </p>

                                    <p class="text-sm text-muted-foreground">
                                        {{ attemptCounts(attempt) }}
                                    </p>

                                    <CollapsibleTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="min-h-11 justify-self-start md:min-h-9"
                                        >
                                            {{
                                                openAttempt === attempt.id
                                                    ? 'Hide items'
                                                    : 'Show items'
                                            }}
                                            <ChevronDownIcon
                                                class="transition-transform"
                                                :class="
                                                    openAttempt === attempt.id
                                                        ? 'rotate-180'
                                                        : ''
                                                "
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </CollapsibleTrigger>
                                </div>

                                <CollapsibleContent>
                                    <div class="border-t p-4">
                                        <CustomReviewAttemptItems
                                            :attempt="attempt"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>
                    </li>
                </ul>
            </template>
        </section>

        <CustomReviewDeleteDialog
            v-model:open="deleteOpen"
            :name="review.name"
            :attempt-count="props.attempts.length"
            :delete-url="props.delete_url"
        />

        <AlertDialog v-model:open="clearOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle> Clear this review? </AlertDialogTitle>
                    <AlertDialogDescription>
                        This takes all {{ reviewItemCountLabel(count) }} out of
                        {{ review.name }}. The review itself stays, empty, and
                        you can add to it again. This cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel data-autofocus :disabled="busy">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        :disabled="busy"
                        @click="clearAll"
                    >
                        Clear review
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
