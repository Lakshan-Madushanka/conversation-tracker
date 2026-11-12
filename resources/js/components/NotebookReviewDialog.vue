<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { TriangleAlertIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    loadNotebookReviewCandidates,
    newNotebookReviewKey,
    NOTEBOOK_REVIEW_KINDS,
    NOTEBOOK_REVIEW_KIND_LABELS,
    sendNotebookReview,
} from '@/lib/notebook-review';
import type {
    NotebookReviewAnswer,
    NotebookReviewCandidateData,
    NotebookReviewCandidatesData,
    NotebookReviewKind,
} from '@/types';

/**
 * Create review and practise, asked from the notebook board.
 *
 * The learner presses one button and this asks the server what the notebook
 * offers now. Up to the maximum, every saved item joins; above it, the learner
 * chooses and nothing is taken silently. Past the recommended size, Continue
 * needs agreeing to first. Every question is the server's decision, answered
 * in its own words; cancelling any of them saves nothing.
 *
 * If the review is saved but its first attempt cannot start, the review stays
 * and is offered here with a retry, so the learner never makes it twice.
 *
 * @see design/feature-Notebooks.md requirements 57-67
 */
const props = defineProps<{
    open: boolean;
    notebookId: number;
    notebookName: string;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

type Phase =
    | 'loading'
    | 'review'
    | 'selection'
    | 'size'
    | 'start_failed'
    | 'error';

const phase = ref<Phase>('loading');
const candidates = ref<NotebookReviewCandidateData[]>([]);
const count = ref(0);
const eligibleCount = ref(0);
const recommended = ref(20);
const maximum = ref(50);
const kinds = ref<NotebookReviewKind[]>([...NOTEBOOK_REVIEW_KINDS]);
const selected = ref<string[]>([]);
/** Whether membership came from the learner's choice above the maximum. */
const selectionActive = ref(false);
const notice = ref('');
const busy = ref(false);
const creationKey = ref(newNotebookReviewKey());
const reviewUrl = ref<string | null>(null);
const lastConfirmed = ref(false);
/** What Try again should repeat: reading the items, or one creation. */
const retryAction = ref<'load' | 'submit'>('load');

const kindChoices = NOTEBOOK_REVIEW_KINDS.map((kind) => ({
    value: kind,
    label: NOTEBOOK_REVIEW_KIND_LABELS[kind],
}));

/** How many items the new review would hold right now. */
const totalSelected = computed(() =>
    selectionActive.value ? selected.value.length : count.value,
);

/** How many of those can actually be practised now. */
const eligibleSelected = computed(() =>
    selectionActive.value
        ? candidates.value.filter(
              (candidate) =>
                  candidate.eligible &&
                  selected.value.includes(candidate.identity),
          ).length
        : eligibleCount.value,
);

const canCreate = computed(
    () =>
        !busy.value &&
        totalSelected.value >= 1 &&
        totalSelected.value <= maximum.value &&
        eligibleSelected.value >= 1,
);

const atMaximum = computed(() => selected.value.length >= maximum.value);

const ineligible = computed(() =>
    candidates.value.filter((candidate) => !candidate.eligible),
);

const groupedCandidates = computed(() =>
    NOTEBOOK_REVIEW_KINDS.map((kind) => ({
        kind,
        label: NOTEBOOK_REVIEW_KIND_LABELS[kind],
        items: candidates.value.filter((candidate) => candidate.kind === kind),
    })).filter((group) => group.items.length > 0),
);

const title = computed(() => {
    switch (phase.value) {
        case 'selection':
            return 'Choose items to practise';
        case 'size':
            return `Practise ${totalSelected.value} items?`;
        case 'start_failed':
            return 'Practice could not start';
        default:
            return `Practise ${props.notebookName}`;
    }
});

function close(): void {
    emit('update:open', false);
}

function onOpenChange(open: boolean): void {
    emit('update:open', open);
}

function reset(): void {
    phase.value = 'loading';
    candidates.value = [];
    count.value = 0;
    eligibleCount.value = 0;
    recommended.value = 20;
    maximum.value = 50;
    kinds.value = [...NOTEBOOK_REVIEW_KINDS];
    selected.value = [];
    selectionActive.value = false;
    notice.value = '';
    busy.value = false;
    creationKey.value = newNotebookReviewKey();
    reviewUrl.value = null;
    lastConfirmed.value = false;
    retryAction.value = 'load';

    void load();
}

async function load(): Promise<void> {
    phase.value = 'loading';
    busy.value = true;
    notice.value = '';

    try {
        apply(
            await loadNotebookReviewCandidates(props.notebookId, kinds.value),
        );
    } catch {
        phase.value = 'error';
        notice.value =
            'The notebook items could not be read. Try again in a moment.';
        retryAction.value = 'load';
    } finally {
        busy.value = false;
    }
}

/** The saved items decide the flow: all of them, a choice, or nothing. */
function apply(data: NotebookReviewCandidatesData): void {
    candidates.value = data.candidates;
    count.value = data.count;
    eligibleCount.value = data.eligible_count;
    recommended.value = data.recommended;
    maximum.value = data.maximum;
    selected.value = [];

    if (count.value === 0) {
        selectionActive.value = false;
        phase.value = 'error';
        notice.value = 'There is nothing in this notebook to review yet.';
        retryAction.value = 'load';

        return;
    }

    if (count.value > maximum.value) {
        selectionActive.value = true;
        phase.value = 'selection';

        return;
    }

    selectionActive.value = false;
    phase.value = 'review';
}

function toggleKind(kind: NotebookReviewKind): void {
    if (busy.value) {
        return;
    }

    const chosen = kinds.value.includes(kind);

    // At least one kind stays, because a review that allows nothing could
    // hold nothing.
    if (chosen && kinds.value.length === 1) {
        return;
    }

    kinds.value = NOTEBOOK_REVIEW_KINDS.filter((candidate) =>
        candidate === kind ? !chosen : kinds.value.includes(candidate),
    );

    void load();
}

function toggleCandidate(identity: string): void {
    if (busy.value) {
        return;
    }

    if (selected.value.includes(identity)) {
        selected.value = selected.value.filter((chosen) => chosen !== identity);

        return;
    }

    if (atMaximum.value) {
        return;
    }

    selected.value = [...selected.value, identity];
}

/** The first press: warn about a long review, or create it straight away. */
function start(): void {
    if (!canCreate.value) {
        return;
    }

    if (totalSelected.value > recommended.value) {
        phase.value = 'size';

        return;
    }

    lastConfirmed.value = false;
    void submit(false);
}

/** The warning's Continue: the same creation, now agreed to. */
function confirmSize(): void {
    if (!canCreate.value) {
        return;
    }

    lastConfirmed.value = true;
    void submit(true);
}

async function submit(confirmed: boolean): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    notice.value = '';

    try {
        await handle(
            await sendNotebookReview(props.notebookId, {
                creationKey: creationKey.value,
                kinds: kinds.value,
                identities: selectionActive.value
                    ? [...selected.value]
                    : undefined,
                confirmed,
            }),
        );
    } catch {
        // The server decides whether the review was saved; a retry sends the
        // same creation key, so it can only ever find that one review.
        phase.value = 'error';
        notice.value = 'The review could not be created just now. Try again.';
        retryAction.value = 'submit';
    } finally {
        busy.value = false;
    }
}

async function handle(answer: NotebookReviewAnswer): Promise<void> {
    reviewUrl.value = answer.review_url;

    switch (answer.outcome) {
        case 'created':
            router.visit(answer.attempt_url);

            return;

        case 'created_start_failed':
            phase.value = 'start_failed';
            notice.value = answer.message;
            retryAction.value = 'submit';

            return;

        case 'needs_selection':
            count.value = answer.count;
            maximum.value = answer.maximum;
            selectionActive.value = true;
            selected.value = [];
            phase.value = 'selection';

            return;

        case 'needs_confirmation':
            count.value = answer.count;
            maximum.value = answer.maximum;
            phase.value = 'size';

            return;

        case 'too_many_selected':
            phase.value = 'selection';
            notice.value = answer.message;

            return;

        case 'stale_selection':
            await load();
            notice.value = answer.message;

            return;

        default:
            phase.value = 'error';
            notice.value = answer.message;
            retryAction.value = 'load';
    }
}

function retry(): void {
    notice.value = '';

    if (retryAction.value === 'load') {
        void load();

        return;
    }

    void submit(lastConfirmed.value);
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="props.open" @update:open="onOpenChange">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    <template v-if="phase === 'review'">
                        This creates a new saved review from this notebook and
                        marks it ready to practise. It does not change the items
                        themselves.
                    </template>
                    <template v-else-if="phase === 'selection'">
                        This notebook holds more items than one review can. Pick
                        the ones to practise — nothing is chosen for you.
                    </template>
                    <template v-else-if="phase === 'size'">
                        Long reviews take a while to practise. Continue to
                        create it, or cancel to change the kinds or the items.
                    </template>
                    <template v-else-if="phase === 'start_failed'">
                        Your review was saved. Only starting its practice did
                        not go through.
                    </template>
                    <template v-else>
                        A new saved review from this notebook's saved items.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="phase === 'loading'"
                class="space-y-2"
                aria-hidden="true"
            >
                <div class="h-4 w-2/3 animate-pulse rounded bg-muted"></div>
                <div class="h-4 w-1/2 animate-pulse rounded bg-muted"></div>
                <div class="h-4 w-3/5 animate-pulse rounded bg-muted"></div>
            </div>

            <p
                v-if="notice !== ''"
                role="alert"
                class="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm"
            >
                <TriangleAlertIcon
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ notice }}</span>
            </p>

            <fieldset
                v-if="phase === 'review' || phase === 'selection'"
                class="space-y-2"
            >
                <legend class="text-sm font-medium">Kinds to include</legend>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="choice in kindChoices"
                        :key="choice.value"
                        class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm md:min-h-9"
                    >
                        <Checkbox
                            :model-value="kinds.includes(choice.value)"
                            :disabled="
                                busy ||
                                (kinds.length === 1 &&
                                    kinds.includes(choice.value))
                            "
                            :aria-label="`Include ${choice.label}`"
                            @update:model-value="toggleKind(choice.value)"
                        />
                        {{ choice.label }}
                    </label>
                </div>
            </fieldset>

            <p v-if="phase === 'review'" aria-live="polite" class="text-sm">
                This review will hold
                <strong>{{ count }} {{ count === 1 ? 'item' : 'items' }}</strong
                >. {{ eligibleSelected }} can be practised now.
            </p>

            <div v-if="phase === 'review' && ineligible.length > 0">
                <p class="text-sm font-medium">Cannot be practised yet</p>
                <ul class="mt-1 space-y-1 text-sm text-muted-foreground">
                    <li
                        v-for="candidate in ineligible"
                        :key="candidate.identity"
                        class="break-words"
                    >
                        <span class="font-medium text-foreground">{{
                            candidate.title
                        }}</span>
                        — {{ candidate.reason }}
                    </li>
                </ul>
                <p class="mt-2 text-xs text-muted-foreground">
                    You can add the missing meanings, or bold the key part of a
                    grammar note, right here in the notebook.
                </p>
            </div>

            <p
                v-if="phase === 'review' && eligibleSelected === 0"
                class="text-sm text-muted-foreground"
            >
                None of these can be practised yet. Fix the entries above before
                starting a review.
            </p>

            <div v-if="phase === 'selection'" class="space-y-3">
                <p aria-live="polite" class="text-sm">
                    {{ count }} items in this notebook. Choose up to
                    {{ maximum }} — {{ selected.length }} selected,
                    {{ eligibleSelected }} can be practised now.
                </p>

                <div class="space-y-4">
                    <section
                        v-for="group in groupedCandidates"
                        :key="group.kind"
                    >
                        <h3 class="text-sm font-semibold">{{ group.label }}</h3>
                        <ul class="mt-1 space-y-1">
                            <li
                                v-for="(candidate, index) in group.items"
                                :key="candidate.identity"
                            >
                                <label
                                    :for="`notebook-review-item-${group.kind}-${index}`"
                                    class="flex min-h-11 cursor-pointer items-start gap-3 rounded-md border px-3 py-2"
                                >
                                    <Checkbox
                                        :id="`notebook-review-item-${group.kind}-${index}`"
                                        class="mt-0.5"
                                        :model-value="
                                            selected.includes(
                                                candidate.identity,
                                            )
                                        "
                                        :disabled="
                                            busy ||
                                            (!selected.includes(
                                                candidate.identity,
                                            ) &&
                                                atMaximum)
                                        "
                                        @update:model-value="
                                            toggleCandidate(candidate.identity)
                                        "
                                    />
                                    <span class="min-w-0 flex-1 space-y-0.5">
                                        <span
                                            class="block text-sm font-medium break-words"
                                        >
                                            {{ candidate.title }}
                                        </span>
                                        <span
                                            v-if="candidate.meaning"
                                            class="block text-xs break-words text-muted-foreground"
                                        >
                                            {{ candidate.meaning }}
                                        </span>
                                        <Badge
                                            v-if="!candidate.eligible"
                                            variant="secondary"
                                            class="mt-1"
                                        >
                                            Cannot practise yet:
                                            {{ candidate.reason }}
                                        </Badge>
                                    </span>
                                </label>
                            </li>
                        </ul>
                    </section>
                </div>

                <p
                    v-if="selected.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Choose at least one item to continue.
                </p>
                <p
                    v-else-if="eligibleSelected === 0"
                    class="text-sm text-muted-foreground"
                >
                    None of the chosen items can be practised yet. Choose one
                    that can, or fix the entries in the notebook.
                </p>
            </div>

            <p v-if="phase === 'size'" aria-live="polite" class="text-sm">
                This review will hold
                <strong>{{ totalSelected }} items</strong>, and
                {{ eligibleSelected }} of them can be practised now.
            </p>

            <DialogFooter class="gap-2">
                <template v-if="phase === 'review' || phase === 'selection'">
                    <Button
                        type="button"
                        variant="ghost"
                        class="min-h-11 md:min-h-9"
                        @click="close"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="min-h-11 md:min-h-9"
                        :disabled="!canCreate"
                        @click="start"
                    >
                        Create review and practise
                    </Button>
                </template>

                <template v-else-if="phase === 'size'">
                    <Button
                        type="button"
                        variant="ghost"
                        class="min-h-11 md:min-h-9"
                        @click="close"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="min-h-11 md:min-h-9"
                        :disabled="busy"
                        @click="confirmSize"
                    >
                        Continue
                    </Button>
                </template>

                <template v-else-if="phase === 'start_failed'">
                    <Button
                        type="button"
                        variant="ghost"
                        class="min-h-11 md:min-h-9"
                        @click="close"
                    >
                        Close
                    </Button>
                    <Button
                        v-if="reviewUrl !== null"
                        variant="outline"
                        class="min-h-11 md:min-h-9"
                        as-child
                    >
                        <a :href="reviewUrl">Open review</a>
                    </Button>
                    <Button
                        type="button"
                        class="min-h-11 md:min-h-9"
                        :disabled="busy"
                        @click="retry"
                    >
                        Try again
                    </Button>
                </template>

                <template v-else-if="phase === 'error'">
                    <Button
                        type="button"
                        variant="ghost"
                        class="min-h-11 md:min-h-9"
                        @click="close"
                    >
                        Close
                    </Button>
                    <Button
                        type="button"
                        class="min-h-11 md:min-h-9"
                        :disabled="busy"
                        @click="retry"
                    >
                        Try again
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
