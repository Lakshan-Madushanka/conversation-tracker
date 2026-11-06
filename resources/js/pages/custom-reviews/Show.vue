<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    ArrowUpRightIcon,
    ListChecksIcon,
    Trash2Icon,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    clearReview,
    removeItemsFromReview,
    reviewItemCountLabel,
    reviewItemName,
    sendAddToReview,
} from '@/lib/custom-review';
import { shortDate } from '@/lib/format';
import { index as collectionsIndex } from '@/routes/collections';
import type {
    CustomReviewGroupData,
    CustomReviewItemData,
    CustomReviewKind,
    CustomReviewPageData,
} from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<CustomReviewPageData>();

/** The items on screen, read live from their sessions by the server. */
const groups = ref<CustomReviewGroupData[]>(cloneGroups(props.groups));

/** True while a remove or an undo is on its way, so none can overlap. */
const busy = ref(false);

const selectedIds = ref<Set<string>>(new Set());

const clearOpen = ref(false);

const items = computed(() => groups.value.flatMap((group) => group.items));

const count = computed(() => items.value.length);

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
        : `${reviewItemCountLabel(count.value)} · ${props.review.status_label}`,
);

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
        <Head :title="props.review.name" />

        <div class="mb-4">
            <Button variant="ghost" size="sm" as-child>
                <Link :href="props.back_url">
                    <ArrowLeftIcon aria-hidden="true" />
                    Back
                </Link>
            </Button>
        </div>

        <PageHeader :title="props.review.name" :description="description">
            <template #actions>
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
            </template>
        </PageHeader>

        <EmptyState
            v-if="count === 0"
            :icon="ListChecksIcon"
            title="Nothing in this review yet"
            description="Choose words, phrases, notes, mistakes or questions in Collections and use Add to review. They will wait here until you are ready."
        >
            <template #actions>
                <Button size="lg" as-child>
                    <Link :href="collectionsIndex.url()">
                        Go to Collections
                    </Link>
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

                                    <p
                                        v-if="!item.available"
                                        class="text-xs font-medium"
                                    >
                                        Not available right now
                                    </p>

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

        <AlertDialog v-model:open="clearOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle> Clear this review? </AlertDialogTitle>
                    <AlertDialogDescription>
                        This takes all {{ reviewItemCountLabel(count) }} out of
                        {{ props.review.name }}. The review itself stays, empty,
                        and you can add to it again. This cannot be undone.
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
