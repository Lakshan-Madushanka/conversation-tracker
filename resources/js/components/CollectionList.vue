<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    BookOpenIcon,
    LoaderCircleIcon,
    PlusIcon,
    SearchIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import AddToBasketButton from '@/components/AddToBasketButton.vue';
import EmptyState from '@/components/EmptyState.vue';
import CollectionMarksCluster from '@/components/CollectionMarksCluster.vue';
import CollectionMatchHint from '@/components/CollectionMatchHint.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { collectionTabConfig } from '@/lib/collection-tabs';
import { collectionCountLabel } from '@/lib/collection-filters';
import { markItemName } from '@/lib/collection-marks';
import {
    isCollectionItem,
    isCollectionMistakeItem,
    isCollectionNoteItem,
    seenLabel,
} from '@/lib/collections';
import { shortDate } from '@/lib/format';
import { create as sessionsCreate } from '@/routes/sessions';
import type {
    CollectionItem,
    CollectionItemStatusValue,
    CollectionMistakeItem,
    CollectionNoteItem,
    CollectionOpenedItem,
    CollectionTabKey,
} from '@/types';

const props = withDefaults(
    defineProps<{
        items: CollectionOpenedItem[];
        /** Which collection is on screen, so the list speaks its language. */
        tab?: CollectionTabKey;
        hasMore?: boolean;
        loading?: boolean;
        error?: boolean;
        updating?: boolean;
        total?: number;
        /** How many items the whole collection holds, before filters. */
        allTotal?: number;
        /** Whether a chip or the search narrows the list. */
        hasFilters?: boolean;
        hasSearch?: boolean;
        /** True when applying a search or filter failed and can be tried again. */
        filterError?: boolean;
        hasFinishedSessions?: boolean;
        /** True while the learner is choosing items to add to the basket. */
        selectionMode?: boolean;
        /** The keys chosen so far. */
        selectedKeys?: string[];
        /** The item whose add request is on its way, so only it waits. */
        busyKey?: string | null;
    }>(),
    {
        tab: 'vocabulary',
        hasMore: false,
        loading: false,
        error: false,
        updating: false,
        total: 0,
        allTotal: undefined,
        hasFilters: false,
        hasSearch: false,
        filterError: false,
        hasFinishedSessions: false,
        selectionMode: false,
        selectedKeys: () => [],
        busyKey: null,
    },
);

const emit = defineEmits<{
    (event: 'open', item: CollectionOpenedItem): void;
    (event: 'load-more'): void;
    (event: 'star', item: CollectionOpenedItem, starred: boolean): void;
    (
        event: 'status',
        item: CollectionOpenedItem,
        status: CollectionItemStatusValue | null,
    ): void;
    (event: 'clear-search'): void;
    (event: 'clear-filters'): void;
    (event: 'retry-filters'): void;
    /** One item chosen or unchosen; Shift chooses a run of items. */
    (event: 'select', item: CollectionOpenedItem, shiftKey: boolean): void;
    /** One item put into the basket without leaving the list. */
    (event: 'add', item: CollectionOpenedItem): void;
}>();

/** How many placeholder rows the list shows while it is busy. */
const skeletonCount = 5;

const config = computed(() => collectionTabConfig(props.tab));

/** Grammar notes list their own pieces: a preview, a topic and a date. */
const notesMode = computed(() => props.tab === 'grammar');

/** Mistakes have their own columns and their own cards with labeled lines. */
const mistakesMode = computed(() => props.tab === 'mistakes');

const groupedItems = computed(
    () => props.items.filter(isCollectionItem) as CollectionItem[],
);

const noteItems = computed(
    () => props.items.filter(isCollectionNoteItem) as CollectionNoteItem[],
);

const mistakeItems = computed(
    () =>
        props.items.filter(isCollectionMistakeItem) as CollectionMistakeItem[],
);

const hasItems = computed(() => props.items.length > 0);

/** Whether a search or a chip narrows the list. The order never counts. */
const filtered = computed(() => props.hasFilters || props.hasSearch);

const settled = computed(
    () => !props.loading && !props.updating && !props.error,
);

const showEmptyState = computed(
    () => !hasItems.value && settled.value && !filtered.value,
);

/** Nothing to search yet: no session has been finished at all. */
const showNoSessions = computed(
    () => !hasItems.value && settled.value && !props.hasFinishedSessions,
);

/** Nothing matched, which is different from a collection with nothing in it. */
const showNoResults = computed(
    () =>
        !hasItems.value &&
        settled.value &&
        filtered.value &&
        props.total === 0 &&
        props.hasFinishedSessions,
);

const allTotal = computed(() => props.allTotal ?? props.total);

const noResultsTitle = computed(() => {
    if (props.hasSearch && props.hasFilters) {
        return 'No matches for this search and these filters';
    }

    if (props.hasSearch) {
        return 'No matches for this search';
    }

    return `No ${config.value.unitPlural} match these filters`;
});

const noResultsDescription = computed(() => {
    if (props.hasSearch && props.hasFilters) {
        return 'Try fewer words, another filter, or clear one of them.';
    }

    if (props.hasSearch) {
        return 'Try another word, or clear the search to see everything again.';
    }

    return 'Try removing a filter to see more of this collection.';
});

/** A screen reader hears the count once the list has settled. */
const announcement = computed(() => {
    if (props.updating || props.loading) {
        return '';
    }

    if (props.total === 0) {
        if (!props.hasFinishedSessions) {
            return 'No finished sessions yet.';
        }

        return filtered.value
            ? `No ${config.value.unitPlural} match.`
            : `No ${config.value.unitPlural} yet.`;
    }

    return `${collectionCountLabel(
        props.total,
        allTotal.value,
        config.value.unit,
        config.value.unitPlural,
        filtered.value,
    )}.`;
});

/** The quiet line under the list once every matching item is on screen. */
const everyItemLabel = computed(() => {
    const many = props.total !== 1;
    const noun = many ? config.value.unitPlural : config.value.unit;
    const whole = many ? 'all' : 'every';

    return filtered.value
        ? `That is ${whole} matching ${noun} — ${props.total} in total.`
        : `That is ${whole} ${noun} — ${props.total} in total.`;
});

function open(item: CollectionOpenedItem): void {
    emit('open', item);
}

/** Clicking a row opens it, or chooses it while a selection is being made. */
function activate(item: CollectionOpenedItem, event?: MouseEvent): void {
    if (props.selectionMode) {
        emit('select', item, event?.shiftKey === true);

        return;
    }

    open(item);
}

const selectedSet = computed(() => new Set(props.selectedKeys));

function isSelected(item: CollectionOpenedItem): boolean {
    return selectedSet.value.has(item.key);
}

const selectNoun = computed(() => config.value.unit);

/** An id safe for a word with spaces or punctuation in its key. */
function selectId(scope: string, item: CollectionOpenedItem): string {
    return `collection-${scope}-select-${encodeURIComponent(item.key)}`;
}

/** The checkbox's name holds the item, so the choice is never ambiguous. */
function selectLabel(item: CollectionOpenedItem): string {
    return `Select the ${selectNoun.value} ${itemName(item)}`;
}

/** What the item is called in words, for the basket's controls. */
function itemName(item: CollectionOpenedItem): string {
    if (isCollectionNoteItem(item)) {
        return noteMarkName(item);
    }

    return markItemName(item);
}

/**
 * The name a mark's accessible label uses for a note: the preview the
 * server already cut, never the whole note.
 */
function noteMarkName(note: CollectionNoteItem): string {
    return note.preview === '' ? note.key : note.preview;
}

/**
 * The mistake a screen reader hears in the row's name: what was said and
 * what to say instead, so the control's name holds the mistake itself.
 */
function mistakeName(item: CollectionMistakeItem): string {
    const said = item.title.trim() === '' ? '(nothing)' : item.title;

    return item.correction.trim() === ''
        ? `${said}, needs the correction`
        : `${said}, correction: ${item.correction}`;
}

function noteDate(note: CollectionNoteItem): string {
    return note.session_date === null ? '' : shortDate(note.session_date);
}

/**
 * The example's words, with only its safe bold and italic kept. The server
 * reduces whatever was saved to the formatting the app supports before the
 * list ever sees it, so the saved text is never put into the page as it was
 * received.
 */
function exampleHtml(item: CollectionItem): string {
    return props.tab === 'phrases' ? item.example_html : item.example;
}
</script>

<template>
    <div>
        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ announcement }}
        </p>

        <div
            v-if="props.updating"
            class="grid gap-3"
            aria-busy="true"
            aria-label="Loading the collection"
        >
            <Skeleton
                v-for="skeleton in skeletonCount"
                :key="skeleton"
                class="h-20 w-full rounded-xl"
            />
        </div>

        <template v-else>
            <div
                v-if="!hasItems && props.loading"
                class="grid gap-3"
                aria-busy="true"
                aria-label="Loading the collection"
            >
                <Skeleton
                    v-for="skeleton in skeletonCount"
                    :key="`first-${skeleton}`"
                    class="h-20 w-full rounded-xl"
                />
            </div>

            <template v-if="hasItems">
                <!-- Grammar notes: a preview, its topic and its date. -->
                <div v-if="notesMode" class="hidden md:block">
                    <Table class="table-fixed">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[44%]">Note</TableHead>
                                <TableHead class="w-[20%]">Topic</TableHead>
                                <TableHead class="w-[16%]">Date</TableHead>
                                <TableHead class="w-[20%] text-right">
                                    <span class="sr-only">{{
                                        selectionMode
                                            ? 'Select'
                                            : 'Star and status'
                                    }}</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="note in noteItems"
                                :key="note.key"
                                class="cursor-pointer"
                                @click="activate(note, $event)"
                            >
                                <TableCell class="whitespace-normal">
                                    <button
                                        type="button"
                                        class="rounded text-left hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                        :data-collection-open="note.key"
                                        @click.stop="activate(note, $event)"
                                    >
                                        {{ note.preview }}
                                    </button>
                                    <CollectionMatchHint
                                        v-if="note.match"
                                        :match="note.match"
                                    />
                                    <div
                                        v-if="!selectionMode"
                                        class="mt-2 flex"
                                    >
                                        <AddToBasketButton
                                            :name="itemName(note)"
                                            :added="note.in_basket === true"
                                            :busy="props.busyKey === note.key"
                                            @add="emit('add', note)"
                                        />
                                    </div>
                                </TableCell>

                                <TableCell
                                    class="whitespace-normal text-muted-foreground"
                                >
                                    {{ note.topic }}
                                </TableCell>

                                <TableCell
                                    class="whitespace-nowrap text-muted-foreground"
                                >
                                    {{ noteDate(note) }}
                                </TableCell>

                                <TableCell class="text-right align-top">
                                    <span
                                        v-if="selectionMode"
                                        class="inline-flex"
                                        @click.stop
                                    >
                                        <Checkbox
                                            :id="selectId('note', note)"
                                            :model-value="isSelected(note)"
                                            :aria-label="selectLabel(note)"
                                            @update:model-value="
                                                emit('select', note, false)
                                            "
                                        />
                                    </span>
                                    <CollectionMarksCluster
                                        v-else
                                        :item="note"
                                        :name="noteMarkName(note)"
                                        :unit="config.unit"
                                        @star="emit('star', note, $event)"
                                        @status="emit('status', note, $event)"
                                    />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- My mistakes on a wide screen: what was said, what to say
                    instead, why, and how often. The correction keeps its own
                    bold and italic, already reduced by the server to
                    formatting the app supports. -->
                <div v-else-if="mistakesMode" class="hidden md:block">
                    <Table class="table-fixed">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[26%]">{{
                                    config.columns[0]
                                }}</TableHead>
                                <TableHead class="w-[26%]">{{
                                    config.columns[1]
                                }}</TableHead>
                                <TableHead class="w-[20%]">{{
                                    config.columns[2]
                                }}</TableHead>
                                <TableHead class="w-[12%] text-right">
                                    <span class="sr-only">Times seen</span>
                                </TableHead>
                                <TableHead class="w-[16%] text-right">
                                    <span class="sr-only">{{
                                        selectionMode
                                            ? 'Select'
                                            : 'Star and status'
                                    }}</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="mistake in mistakeItems"
                                :key="mistake.key"
                                class="cursor-pointer"
                                :aria-label="`Mistake: ${mistakeName(mistake)}`"
                                @click="activate(mistake, $event)"
                            >
                                <TableCell class="whitespace-normal">
                                    <button
                                        type="button"
                                        class="min-h-11 w-full rounded text-left font-medium hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                        :aria-label="`Open mistake: ${mistakeName(mistake)}`"
                                        :data-collection-open="mistake.key"
                                        @click.stop="activate(mistake, $event)"
                                    >
                                        {{ mistake.title }}
                                    </button>
                                    <Badge
                                        v-if="mistake.worth_practicing"
                                        variant="outline"
                                        class="mt-1 align-middle"
                                    >
                                        Worth practicing
                                    </Badge>
                                    <Badge
                                        v-if="mistake.needs_attention"
                                        variant="secondary"
                                        class="ml-0 align-middle sm:ml-2"
                                    >
                                        Needs attention
                                    </Badge>
                                    <CollectionMatchHint
                                        v-if="mistake.match"
                                        :match="mistake.match"
                                    />
                                    <div
                                        v-if="!selectionMode"
                                        class="mt-2 flex"
                                    >
                                        <AddToBasketButton
                                            :name="itemName(mistake)"
                                            :added="mistake.in_basket === true"
                                            :busy="
                                                props.busyKey === mistake.key
                                            "
                                            @add="emit('add', mistake)"
                                        />
                                    </div>
                                </TableCell>

                                <TableCell
                                    class="whitespace-normal text-muted-foreground"
                                >
                                    <span
                                        v-if="mistake.correction_html"
                                        class="line-clamp-2 text-foreground"
                                        v-html="mistake.correction_html"
                                    ></span>
                                    <span
                                        v-else-if="mistake.correction"
                                        class="line-clamp-2 text-foreground"
                                        >{{ mistake.correction }}</span
                                    >
                                    <span v-else class="italic">{{
                                        config.missingCorrection
                                    }}</span>
                                </TableCell>

                                <TableCell
                                    class="whitespace-normal text-muted-foreground"
                                >
                                    <span v-if="mistake.reason">{{
                                        mistake.reason
                                    }}</span>
                                    <span v-else class="italic">{{
                                        config.missingReason
                                    }}</span>
                                </TableCell>

                                <TableCell class="text-right align-top">
                                    <Badge variant="outline">
                                        {{ seenLabel(mistake.seen_count) }}
                                    </Badge>
                                </TableCell>

                                <TableCell class="text-right align-top">
                                    <span
                                        v-if="selectionMode"
                                        class="inline-flex"
                                        @click.stop
                                    >
                                        <Checkbox
                                            :id="selectId('mistake', mistake)"
                                            :model-value="isSelected(mistake)"
                                            :aria-label="selectLabel(mistake)"
                                            @update:model-value="
                                                emit('select', mistake, false)
                                            "
                                        />
                                    </span>
                                    <CollectionMarksCluster
                                        v-else
                                        :item="mistake"
                                        :name="markItemName(mistake)"
                                        :unit="config.unit"
                                        @star="emit('star', mistake, $event)"
                                        @status="
                                            emit('status', mistake, $event)
                                        "
                                    />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- A table is the easiest way to compare many words on a wide
                    screen. It never needs sideways scrolling: long meanings and
                    examples wrap instead. -->
                <div v-else class="hidden md:block">
                    <Table class="table-fixed">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[18%]">{{
                                    config.columns[0]
                                }}</TableHead>
                                <TableHead class="w-[24%]">{{
                                    config.columns[1]
                                }}</TableHead>
                                <TableHead class="w-[30%]">{{
                                    config.columns[2]
                                }}</TableHead>
                                <TableHead class="w-[12%] text-right">
                                    <span class="sr-only">Times seen</span>
                                </TableHead>
                                <TableHead class="w-[16%] text-right">
                                    <span class="sr-only">{{
                                        selectionMode
                                            ? 'Select'
                                            : 'Star and status'
                                    }}</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="item in groupedItems"
                                :key="item.key"
                                class="cursor-pointer"
                                @click="activate(item, $event)"
                            >
                                <TableCell class="whitespace-normal">
                                    <button
                                        type="button"
                                        class="rounded text-left font-medium hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                        :data-collection-open="item.key"
                                        @click.stop="activate(item, $event)"
                                    >
                                        {{ item.title }}
                                    </button>
                                    <Badge
                                        v-if="item.needs_attention"
                                        variant="secondary"
                                        class="ml-2 align-middle"
                                    >
                                        Needs attention
                                    </Badge>
                                    <CollectionMatchHint
                                        v-if="item.match"
                                        :match="item.match"
                                    />
                                    <div
                                        v-if="!selectionMode"
                                        class="mt-2 flex"
                                    >
                                        <AddToBasketButton
                                            :name="itemName(item)"
                                            :added="item.in_basket === true"
                                            :busy="props.busyKey === item.key"
                                            @add="emit('add', item)"
                                        />
                                    </div>
                                </TableCell>

                                <TableCell
                                    class="whitespace-normal text-muted-foreground"
                                >
                                    <span v-if="item.meaning">{{
                                        item.meaning
                                    }}</span>
                                    <span
                                        v-else-if="config.missingMeaning"
                                        class="italic"
                                        >{{ config.missingMeaning }}</span
                                    >
                                </TableCell>

                                <TableCell
                                    class="whitespace-normal text-muted-foreground"
                                >
                                    <!-- The phrase example keeps its own
                                        bold and italic, already reduced by
                                        the server to formatting the app
                                        supports; a word example stays plain
                                        text as it always was. -->
                                    <span
                                        v-if="
                                            item.example &&
                                            props.tab === 'phrases' &&
                                            item.example_html
                                        "
                                        class="line-clamp-2"
                                    >
                                        “<span v-html="exampleHtml(item)"></span
                                        >”
                                    </span>
                                    <span
                                        v-else-if="item.example"
                                        class="line-clamp-2"
                                        >“{{ item.example }}”</span
                                    >
                                    <span v-else aria-hidden="true">—</span>
                                </TableCell>

                                <TableCell class="text-right align-middle">
                                    <Badge
                                        v-if="item.seen_count > 1"
                                        variant="outline"
                                    >
                                        {{ seenLabel(item.seen_count) }}
                                    </Badge>
                                </TableCell>

                                <TableCell class="text-right align-middle">
                                    <span
                                        v-if="selectionMode"
                                        class="inline-flex"
                                        @click.stop
                                    >
                                        <Checkbox
                                            :id="selectId('item', item)"
                                            :model-value="isSelected(item)"
                                            :aria-label="selectLabel(item)"
                                            @update:model-value="
                                                emit('select', item, false)
                                            "
                                        />
                                    </span>
                                    <CollectionMarksCluster
                                        v-else
                                        :item="item"
                                        :name="markItemName(item)"
                                        :unit="config.unit"
                                        @star="emit('star', item, $event)"
                                        @status="emit('status', item, $event)"
                                    />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- On a phone each item becomes a card, so nothing is squeezed
                    into a narrow table. -->
                <div class="grid gap-3 md:hidden">
                    <template v-if="notesMode">
                        <Card
                            v-for="note in noteItems"
                            :key="note.key"
                            class="py-0"
                        >
                            <button
                                type="button"
                                class="w-full p-4 text-left focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                :data-collection-open="note.key"
                                @click="activate(note, $event)"
                            >
                                <span class="block text-sm break-words">
                                    {{ note.preview }}
                                </span>
                                <span
                                    class="mt-2 block text-xs text-muted-foreground"
                                >
                                    {{ note.topic }} · {{ noteDate(note) }}
                                </span>
                                <CollectionMatchHint
                                    v-if="note.match"
                                    :match="note.match"
                                />
                            </button>
                            <div
                                class="flex min-h-12 items-center justify-between gap-2 border-t px-4 py-1"
                            >
                                <span
                                    v-if="selectionMode"
                                    class="flex items-center gap-2 pl-1"
                                    @click.stop
                                >
                                    <Checkbox
                                        :id="selectId('note-card', note)"
                                        :model-value="isSelected(note)"
                                        :aria-label="selectLabel(note)"
                                        @update:model-value="
                                            emit('select', note, false)
                                        "
                                    />
                                    <Label
                                        :for="selectId('note-card', note)"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Select
                                    </Label>
                                </span>
                                <template v-else>
                                    <AddToBasketButton
                                        :name="itemName(note)"
                                        :added="note.in_basket === true"
                                        :busy="props.busyKey === note.key"
                                        @add="emit('add', note)"
                                    />
                                    <CollectionMarksCluster
                                        :item="note"
                                        :name="noteMarkName(note)"
                                        :unit="config.unit"
                                        @star="emit('star', note, $event)"
                                        @status="emit('status', note, $event)"
                                    />
                                </template>
                            </div>
                        </Card>
                    </template>

                    <template v-else-if="mistakesMode">
                        <!-- Each mistake becomes a card with printed labels:
                            the words are read as words, not by an arrow. -->
                        <Card
                            v-for="mistake in mistakeItems"
                            :key="mistake.key"
                            class="py-0"
                        >
                            <button
                                type="button"
                                class="min-h-11 w-full p-4 text-left focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                :aria-label="`Open mistake: ${mistakeName(mistake)}`"
                                :data-collection-open="mistake.key"
                                @click="activate(mistake, $event)"
                            >
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ config.columns[0] }}
                                </span>
                                <span
                                    class="mt-0.5 flex flex-wrap items-center gap-2"
                                >
                                    <span class="font-semibold break-words">
                                        {{ mistake.title }}
                                    </span>
                                </span>

                                <span
                                    class="mt-2 block text-xs text-muted-foreground"
                                >
                                    {{ config.columns[1] }}
                                </span>
                                <span class="mt-0.5 block text-sm break-words">
                                    <span
                                        v-if="mistake.correction_html"
                                        v-html="mistake.correction_html"
                                    ></span>
                                    <span v-else-if="mistake.correction">
                                        {{ mistake.correction }}
                                    </span>
                                    <span v-else class="italic">
                                        {{ config.missingCorrection }}
                                    </span>
                                </span>

                                <span
                                    class="mt-2 block text-xs text-muted-foreground"
                                >
                                    {{ config.columns[2] }}
                                </span>
                                <span
                                    v-if="mistake.reason"
                                    class="mt-0.5 block text-sm break-words"
                                >
                                    {{ mistake.reason }}
                                </span>
                                <span
                                    v-else
                                    class="mt-0.5 block text-sm italic"
                                >
                                    {{ config.missingReason }}
                                </span>

                                <span
                                    class="mt-3 flex flex-wrap items-center gap-2"
                                >
                                    <Badge variant="outline">
                                        {{ seenLabel(mistake.seen_count) }}
                                    </Badge>
                                    <Badge
                                        v-if="mistake.worth_practicing"
                                        variant="outline"
                                    >
                                        Worth practicing
                                    </Badge>
                                    <Badge
                                        v-if="mistake.needs_attention"
                                        variant="secondary"
                                    >
                                        Needs attention
                                    </Badge>
                                </span>

                                <CollectionMatchHint
                                    v-if="mistake.match"
                                    :match="mistake.match"
                                />
                            </button>
                            <div
                                class="flex min-h-12 items-center justify-between gap-2 border-t px-4 py-1"
                            >
                                <span
                                    v-if="selectionMode"
                                    class="flex items-center gap-2 pl-1"
                                    @click.stop
                                >
                                    <Checkbox
                                        :id="selectId('mistake-card', mistake)"
                                        :model-value="isSelected(mistake)"
                                        :aria-label="selectLabel(mistake)"
                                        @update:model-value="
                                            emit('select', mistake, false)
                                        "
                                    />
                                    <Label
                                        :for="selectId('mistake-card', mistake)"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Select
                                    </Label>
                                </span>
                                <template v-else>
                                    <AddToBasketButton
                                        :name="itemName(mistake)"
                                        :added="mistake.in_basket === true"
                                        :busy="props.busyKey === mistake.key"
                                        @add="emit('add', mistake)"
                                    />
                                    <CollectionMarksCluster
                                        :item="mistake"
                                        :name="markItemName(mistake)"
                                        :unit="config.unit"
                                        @star="emit('star', mistake, $event)"
                                        @status="
                                            emit('status', mistake, $event)
                                        "
                                    />
                                </template>
                            </div>
                        </Card>
                    </template>

                    <template v-else>
                        <Card
                            v-for="item in groupedItems"
                            :key="item.key"
                            class="py-0"
                        >
                            <button
                                type="button"
                                class="w-full p-4 text-left focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                :data-collection-open="item.key"
                                @click="activate(item, $event)"
                            >
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{
                                        item.title
                                    }}</span>
                                    <Badge
                                        v-if="item.needs_attention"
                                        variant="secondary"
                                    >
                                        Needs attention
                                    </Badge>
                                    <Badge
                                        v-if="item.seen_count > 1"
                                        variant="outline"
                                    >
                                        {{ seenLabel(item.seen_count) }}
                                    </Badge>
                                </span>

                                <span
                                    v-if="item.meaning"
                                    class="mt-1 block text-sm text-muted-foreground"
                                >
                                    {{ item.meaning }}
                                </span>
                                <span
                                    v-else-if="config.missingMeaning"
                                    class="mt-1 block text-sm text-muted-foreground italic"
                                >
                                    {{ config.missingMeaning }}
                                </span>

                                <span
                                    v-if="
                                        item.example &&
                                        props.tab === 'phrases' &&
                                        item.example_html
                                    "
                                    class="mt-1 block text-sm text-muted-foreground italic"
                                >
                                    “<span v-html="exampleHtml(item)"></span>”
                                </span>
                                <span
                                    v-else-if="item.example"
                                    class="mt-1 block text-sm text-muted-foreground italic"
                                >
                                    “{{ item.example }}”
                                </span>

                                <CollectionMatchHint
                                    v-if="item.match"
                                    :match="item.match"
                                />
                            </button>
                            <div
                                class="flex min-h-12 items-center justify-between gap-2 border-t px-4 py-1"
                            >
                                <span
                                    v-if="selectionMode"
                                    class="flex items-center gap-2 pl-1"
                                    @click.stop
                                >
                                    <Checkbox
                                        :id="selectId('item-card', item)"
                                        :model-value="isSelected(item)"
                                        :aria-label="selectLabel(item)"
                                        @update:model-value="
                                            emit('select', item, false)
                                        "
                                    />
                                    <Label
                                        :for="selectId('item-card', item)"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Select
                                    </Label>
                                </span>
                                <template v-else>
                                    <AddToBasketButton
                                        :name="itemName(item)"
                                        :added="item.in_basket === true"
                                        :busy="props.busyKey === item.key"
                                        @add="emit('add', item)"
                                    />
                                    <CollectionMarksCluster
                                        :item="item"
                                        :name="markItemName(item)"
                                        :unit="config.unit"
                                        @star="emit('star', item, $event)"
                                        @status="emit('status', item, $event)"
                                    />
                                </template>
                            </div>
                        </Card>
                    </template>
                </div>

                <div
                    v-if="props.loading"
                    class="mt-3 grid gap-3"
                    aria-busy="true"
                    aria-label="Loading more items"
                >
                    <Skeleton
                        v-for="skeleton in skeletonCount"
                        :key="`skeleton-${skeleton}`"
                        class="h-20 w-full rounded-xl"
                    />
                </div>
            </template>

            <EmptyState
                v-else-if="showNoResults"
                :icon="SearchIcon"
                :title="noResultsTitle"
                :description="noResultsDescription"
            >
                <template #actions>
                    <Button
                        v-if="props.hasSearch"
                        type="button"
                        size="lg"
                        @click="emit('clear-search')"
                    >
                        Clear search
                    </Button>
                    <Button
                        v-if="props.hasFilters"
                        type="button"
                        variant="outline"
                        size="lg"
                        @click="emit('clear-filters')"
                    >
                        Clear filters
                    </Button>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="showNoSessions"
                :icon="BookOpenIcon"
                title="Your collections are waiting"
                description="Finish a practice session and what you save will be gathered here automatically."
            >
                <template #actions>
                    <Button size="lg" as-child>
                        <Link :href="sessionsCreate.url()">
                            <PlusIcon aria-hidden="true" />
                            Start a session
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="showEmptyState"
                :icon="BookOpenIcon"
                :title="config.emptyTitle"
                :description="config.emptyDescription"
            >
                <template #actions>
                    <Button size="lg" as-child>
                        <Link :href="sessionsCreate.url()">
                            <PlusIcon aria-hidden="true" />
                            Start a session
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <Alert v-if="props.error" variant="destructive" class="mt-4">
                <TriangleAlertIcon aria-hidden="true" />
                <AlertTitle>Could not load the collection</AlertTitle>
                <AlertDescription>
                    <p>
                        Your saved items are still here. Check your connection
                        and try again.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="emit('load-more')"
                    >
                        Try again
                    </Button>
                </AlertDescription>
            </Alert>

            <Alert v-if="props.filterError" variant="destructive" class="mt-4">
                <TriangleAlertIcon aria-hidden="true" />
                <AlertTitle>Could not apply those filters</AlertTitle>
                <AlertDescription>
                    <p>
                        Your list is still here. Check your connection and try
                        again.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="emit('retry-filters')"
                    >
                        Try again
                    </Button>
                </AlertDescription>
            </Alert>
        </template>

        <div
            v-if="hasItems && !props.updating"
            class="mt-5 flex flex-col items-center gap-3"
        >
            <Button
                v-if="props.hasMore"
                type="button"
                variant="outline"
                :disabled="props.loading"
                @click="emit('load-more')"
            >
                <LoaderCircleIcon
                    v-if="props.loading"
                    class="animate-spin"
                    aria-hidden="true"
                />
                {{
                    props.loading
                        ? 'Loading…'
                        : `Load more ${config.unitPlural}`
                }}
            </Button>

            <p v-else class="text-xs text-muted-foreground">
                {{ everyItemLabel }}
            </p>

            <p v-if="props.hasMore" class="text-xs text-muted-foreground">
                Showing {{ props.items.length }} of {{ props.total }}
            </p>
        </div>
    </div>
</template>
