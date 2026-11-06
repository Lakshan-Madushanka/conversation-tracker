<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    ArrowUpRightIcon,
    ShoppingBasketIcon,
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
    addToBasket,
    basketCountLabel,
    basketItemName,
    clearBasket,
    hydrateBasketCount,
    removeFromBasket,
} from '@/lib/basket';
import { shortDate } from '@/lib/format';
import { index as collectionsIndex } from '@/routes/collections';
import type {
    BasketGroupData,
    BasketItemData,
    BasketKind,
    BasketPageData,
} from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<BasketPageData>();

/** The groups to show, read live from their sessions by the server. */
const groups = ref<BasketGroupData[]>(cloneGroups(props.groups));

const selectedIds = ref<Set<string>>(new Set());

const clearOpen = ref(false);

/** True while a remove or clear is on its way, so none can overlap. */
const busy = ref(false);

hydrateBasketCount(props.count);

/** The order the groups always keep, whatever order they come back in. */
const GROUP_ORDER: BasketKind[] = [
    'vocabulary',
    'phrase',
    'grammar_note',
    'mistake',
    'question',
];

const count = computed(() =>
    groups.value.reduce((total, group) => total + group.items.length, 0),
);

const items = computed(() => groups.value.flatMap((group) => group.items));

const selectedItems = computed(() =>
    items.value.filter((item) => selectedIds.value.has(item.id)),
);

const selectedCount = computed(() => selectedItems.value.length);

const selectedLabel = computed(() =>
    selectedCount.value === 1
        ? '1 item selected'
        : `${selectedCount.value} items selected`,
);

const description = computed(() =>
    count.value === 0
        ? 'Items you choose in Collections gather here before you make a custom review.'
        : `${basketCountLabel(count.value)} gathered for a custom review. Nothing is saved as a review yet.`,
);

function cloneGroups(source: BasketGroupData[]): BasketGroupData[] {
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

watch(
    () => props.count,
    (value) => hydrateBasketCount(value),
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

function isSelected(item: BasketItemData): boolean {
    return selectedIds.value.has(item.id);
}

function toggleItem(item: BasketItemData): void {
    const next = new Set(selectedIds.value);

    if (next.has(item.id)) {
        next.delete(item.id);
    } else {
        next.add(item.id);
    }

    selectedIds.value = next;
}

/** What one kind of item is called in words, for accessible names. */
function unitFor(kind: BasketKind): string {
    const units: Record<BasketKind, string> = {
        vocabulary: 'word',
        phrase: 'phrase',
        grammar_note: 'grammar note',
        mistake: 'mistake',
        question: 'question',
    };

    return units[kind];
}

function nameFor(item: BasketItemData): string {
    return basketItemName(item.kind, item.title);
}

function selectLabel(item: BasketItemData): string {
    return `Select the ${unitFor(item.kind)} ${nameFor(item)}`;
}

function removeLabel(item: BasketItemData): string {
    return `Remove the ${unitFor(item.kind)} ${nameFor(item)} from the basket`;
}

function checkboxId(item: BasketItemData): string {
    return `basket-select-${encodeURIComponent(item.id)}`;
}

function sourceLabel(item: BasketItemData): string {
    const date = item.session_date === null ? '' : shortDate(item.session_date);

    return [item.topic, date].filter((piece) => piece !== '').join(' · ');
}

/**
 * What was in the basket before a removal, and where it sat, so Undo can put
 * it back exactly where it was instead of at the top.
 */
interface RemovedSnapshot {
    item: BasketItemData;
    label: string;
    index: number;
}

function snapshotsOf(itemsToRemove: BasketItemData[]): RemovedSnapshot[] {
    return itemsToRemove.map((item) => {
        const group = groups.value.find(
            (candidate) => candidate.key === item.kind,
        );
        const index =
            group?.items.findIndex((candidate) => candidate.id === item.id) ??
            0;

        return { item, label: group?.label ?? item.kind, index };
    });
}

function groupByKind(
    itemsToGroup: BasketItemData[],
): Map<BasketKind, BasketItemData[]> {
    const grouped = new Map<BasketKind, BasketItemData[]>();

    for (const item of itemsToGroup) {
        const list = grouped.get(item.kind) ?? [];
        list.push(item);
        grouped.set(item.kind, list);
    }

    return grouped;
}

/** Takes removed items out of the screen and the selection at once. */
function applyRemoved(removed: RemovedSnapshot[]): void {
    const removedIds = new Set(removed.map((snapshot) => snapshot.item.id));

    groups.value = groups.value
        .map((group) => {
            const remaining = group.items.filter(
                (item) => !removedIds.has(item.id),
            );

            return { ...group, items: remaining, count: remaining.length };
        })
        .filter((group) => group.items.length > 0);

    selectedIds.value = new Set(
        [...selectedIds.value].filter((id) => !removedIds.has(id)),
    );
}

/**
 * Removes the chosen items, one kind at a time. Each kind's answer is applied
 * as it arrives, so a failure midway leaves the screen telling the truth.
 * Returns what was really removed, with the place each item held.
 */
async function removeMany(
    itemsToRemove: BasketItemData[],
): Promise<RemovedSnapshot[]> {
    if (busy.value || itemsToRemove.length === 0) {
        return [];
    }

    const snapshots = snapshotsOf(itemsToRemove);
    const removed: RemovedSnapshot[] = [];

    busy.value = true;

    try {
        for (const [kind, kindItems] of groupByKind(itemsToRemove)) {
            const result = await removeFromBasket(
                kind,
                kindItems.map((item) => item.identity),
            );

            const goneIdentities = new Set(result.removed_identities);
            const gone = snapshots.filter(
                (snapshot) =>
                    snapshot.item.kind === kind &&
                    goneIdentities.has(snapshot.item.identity),
            );

            // An answer that removed nothing means the basket no longer held
            // those rows, so the screen lets them go as well.
            const settled =
                gone.length > 0
                    ? gone
                    : snapshots.filter(
                          (snapshot) => snapshot.item.kind === kind,
                      );

            applyRemoved(settled);
            removed.push(...settled);
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

function removedMessage(removed: RemovedSnapshot[]): string {
    if (removed.length === 1) {
        return `Removed “${nameFor(removed[0].item)}” from your basket.`;
    }

    return `Removed ${removed.length} items from your basket.`;
}

function offerUndo(removed: RemovedSnapshot[]): void {
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

/** Puts the removed items back into their groups, in their old places. */
async function undoRemoval(removed: RemovedSnapshot[]): Promise<void> {
    if (busy.value || removed.length === 0) {
        return;
    }

    busy.value = true;

    try {
        for (const [kind, kindItems] of groupByKind(
            removed.map((snapshot) => snapshot.item),
        )) {
            await addToBasket(
                kind,
                kindItems.map((item) => item.identity),
            );

            insertBack(
                removed.filter((snapshot) => snapshot.item.kind === kind),
            );
        }

        toast.success(
            removed.length === 1
                ? 'Put back in your basket.'
                : `Put ${removed.length} items back in your basket.`,
        );
    } catch {
        toast.error(
            'Could not undo that. Check your connection and try again.',
        );
    } finally {
        busy.value = false;
    }
}

function insertBack(removed: RemovedSnapshot[]): void {
    const grouped = new Map<BasketKind, RemovedSnapshot[]>();

    for (const snapshot of removed) {
        const list = grouped.get(snapshot.item.kind) ?? [];
        list.push(snapshot);
        grouped.set(snapshot.item.kind, list);
    }

    const next = groups.value.map((group) => ({
        ...group,
        items: [...group.items],
    }));

    for (const [kind, snapshots] of grouped) {
        let group = next.find((candidate) => candidate.key === kind);

        if (group === undefined) {
            group = {
                key: kind,
                label: snapshots[0].label,
                count: 0,
                items: [],
            };
            next.push(group);
        }

        for (const snapshot of [...snapshots].sort(
            (left, right) => left.index - right.index,
        )) {
            if (group.items.some((item) => item.id === snapshot.item.id)) {
                continue;
            }

            group.items.splice(
                Math.min(snapshot.index, group.items.length),
                0,
                snapshot.item,
            );
        }

        group.count = group.items.length;
    }

    next.sort(
        (left, right) =>
            GROUP_ORDER.indexOf(left.key) - GROUP_ORDER.indexOf(right.key),
    );

    groups.value = next;
}

/** One row's Remove acts at once, with a few seconds of Undo. */
async function removeOne(item: BasketItemData): Promise<void> {
    const removed = await removeMany([item]);

    offerUndo(removed);
}

/** Remove every chosen row, then offer one Undo for all of them. */
async function removeSelected(): Promise<void> {
    if (selectedCount.value === 0) {
        return;
    }

    const removed = await removeMany(selectedItems.value);

    offerUndo(removed);
}

/** Empty the basket after the warning, with no way back. */
async function clearAll(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        await clearBasket();

        groups.value = [];
        selectedIds.value = new Set();
        clearOpen.value = false;
        toast.success('Basket cleared.');
    } catch {
        toast.error(
            'The basket was not cleared. Nothing changed — check your connection and try again.',
        );
    } finally {
        busy.value = false;
    }
}

/**
 * Coming back from the original session in another tab may leave the basket
 * out of date: an item may have been edited, archived or deleted there. The
 * page asks for the groups again, unless a removal is already under way.
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

    router.reload({ only: ['groups', 'count'] });
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
        <Head title="Review basket" />

        <div class="mb-4">
            <Button variant="ghost" size="sm" as-child>
                <Link :href="props.back_url">
                    <ArrowLeftIcon aria-hidden="true" />
                    Back to collection
                </Link>
            </Button>
        </div>

        <PageHeader title="Review basket" :description="description">
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
                    Clear basket
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="count === 0"
            :icon="ShoppingBasketIcon"
            title="Your basket is empty"
            description="Choose items in Collections with Add to review."
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
                :aria-labelledby="`basket-group-${group.key}`"
            >
                <h2
                    :id="`basket-group-${group.key}`"
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

        <AlertDialog :open="clearOpen" @update:open="clearOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Clear the basket?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This removes {{ basketCountLabel(count) }} from your
                        basket. It cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel :disabled="busy">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        :disabled="busy"
                        @click="clearAll"
                    >
                        Clear basket
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
