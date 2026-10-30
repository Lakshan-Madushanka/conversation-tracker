<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    BookOpenIcon,
    LoaderCircleIcon,
    PlusIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
        hasFinishedSessions?: boolean;
    }>(),
    {
        tab: 'vocabulary',
        hasMore: false,
        loading: false,
        error: false,
        updating: false,
        total: 0,
        hasFinishedSessions: false,
    },
);

const emit = defineEmits<{
    (event: 'open', item: CollectionOpenedItem): void;
    (event: 'load-more'): void;
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

const showEmptyState = computed(
    () => !hasItems.value && !props.loading && !props.updating && !props.error,
);

/** A screen reader hears the count once the list has settled. */
const announcement = computed(() => {
    if (props.updating || props.loading) {
        return '';
    }

    if (props.total === 0) {
        return props.hasFinishedSessions
            ? `No ${config.value.unitPlural} yet.`
            : 'No finished sessions yet.';
    }

    const unit =
        props.total === 1 ? config.value.unit : config.value.unitPlural;

    return `${props.total} ${unit}.`;
});

function open(item: CollectionOpenedItem): void {
    emit('open', item);
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
                                <TableHead class="w-[58%]">Note</TableHead>
                                <TableHead class="w-[24%]">Topic</TableHead>
                                <TableHead class="w-[18%]">Date</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="note in noteItems"
                                :key="note.key"
                                class="cursor-pointer"
                                @click="open(note)"
                            >
                                <TableCell class="whitespace-normal">
                                    <button
                                        type="button"
                                        class="rounded text-left hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                        @click.stop="open(note)"
                                    >
                                        {{ note.preview }}
                                    </button>
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
                                <TableHead class="w-[30%]">{{
                                    config.columns[0]
                                }}</TableHead>
                                <TableHead class="w-[30%]">{{
                                    config.columns[1]
                                }}</TableHead>
                                <TableHead class="w-[24%]">{{
                                    config.columns[2]
                                }}</TableHead>
                                <TableHead class="w-[16%] text-right">
                                    <span class="sr-only">Times seen</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="mistake in mistakeItems"
                                :key="mistake.key"
                                class="cursor-pointer"
                                :aria-label="`Mistake: ${mistakeName(mistake)}`"
                                @click="open(mistake)"
                            >
                                <TableCell class="whitespace-normal">
                                    <button
                                        type="button"
                                        class="min-h-11 w-full rounded text-left font-medium hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                        :aria-label="`Open mistake: ${mistakeName(mistake)}`"
                                        @click.stop="open(mistake)"
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
                                <TableHead class="w-[22%]">{{
                                    config.columns[0]
                                }}</TableHead>
                                <TableHead class="w-[28%]">{{
                                    config.columns[1]
                                }}</TableHead>
                                <TableHead class="w-[38%]">{{
                                    config.columns[2]
                                }}</TableHead>
                                <TableHead class="w-[12%] text-right">
                                    <span class="sr-only">Times seen</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="item in groupedItems"
                                :key="item.key"
                                class="cursor-pointer"
                                @click="open(item)"
                            >
                                <TableCell class="whitespace-normal">
                                    <button
                                        type="button"
                                        class="rounded text-left font-medium hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                        @click.stop="open(item)"
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

                                <TableCell class="text-right align-top">
                                    <Badge
                                        v-if="item.seen_count > 1"
                                        variant="outline"
                                    >
                                        {{ seenLabel(item.seen_count) }}
                                    </Badge>
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
                                @click="open(note)"
                            >
                                <span class="block text-sm break-words">
                                    {{ note.preview }}
                                </span>
                                <span
                                    class="mt-2 block text-xs text-muted-foreground"
                                >
                                    {{ note.topic }} · {{ noteDate(note) }}
                                </span>
                            </button>
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
                                @click="open(mistake)"
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
                            </button>
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
                                @click="open(item)"
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
                            </button>
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
                v-else-if="showEmptyState && !props.hasFinishedSessions"
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
                That is every {{ config.unit }} — {{ props.total }} in total.
            </p>

            <p v-if="props.hasMore" class="text-xs text-muted-foreground">
                Showing {{ props.items.length }} of {{ props.total }}
            </p>
        </div>
    </div>
</template>
