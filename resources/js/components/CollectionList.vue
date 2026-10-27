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
import { create as sessionsCreate } from '@/routes/sessions';
import type { CollectionItem } from '@/types';

const props = withDefaults(
    defineProps<{
        items: CollectionItem[];
        hasMore?: boolean;
        loading?: boolean;
        error?: boolean;
        updating?: boolean;
        total?: number;
        hasFinishedSessions?: boolean;
    }>(),
    {
        hasMore: false,
        loading: false,
        error: false,
        updating: false,
        total: 0,
        hasFinishedSessions: false,
    },
);

const emit = defineEmits<{
    (event: 'open', item: CollectionItem): void;
    (event: 'load-more'): void;
}>();

/** How many placeholder rows the list shows while it is busy. */
const skeletonCount = 5;

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
            ? 'No words yet.'
            : 'No finished sessions yet.';
    }

    return `${props.total} ${props.total === 1 ? 'word' : 'words'}.`;
});

function seenLabel(item: CollectionItem): string {
    return `Seen ${item.seen_count} times`;
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
                aria-label="Loading words"
            >
                <Skeleton
                    v-for="skeleton in skeletonCount"
                    :key="`first-${skeleton}`"
                    class="h-20 w-full rounded-xl"
                />
            </div>

            <template v-if="hasItems">
                <!-- A table is the easiest way to compare many words on a wide
                    screen. It never needs sideways scrolling: long meanings and
                    examples wrap instead. -->
                <div class="hidden md:block">
                    <Table class="table-fixed">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[22%]">Word</TableHead>
                                <TableHead class="w-[28%]">Meaning</TableHead>
                                <TableHead class="w-[38%]">Example</TableHead>
                                <TableHead class="w-[12%] text-right">
                                    <span class="sr-only">Times seen</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="item in props.items"
                                :key="item.key"
                                class="cursor-pointer"
                                @click="emit('open', item)"
                            >
                                <TableCell class="whitespace-normal">
                                    <button
                                        type="button"
                                        class="rounded text-left font-medium hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                        @click.stop="emit('open', item)"
                                    >
                                        {{ item.word }}
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
                                    <span v-else class="italic"
                                        >Add the meaning</span
                                    >
                                </TableCell>

                                <TableCell
                                    class="whitespace-normal text-muted-foreground"
                                >
                                    <span
                                        v-if="item.example"
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
                                        {{ seenLabel(item) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- On a phone each word becomes a card, so nothing is squeezed
                    into a narrow table. -->
                <div class="grid gap-3 md:hidden">
                    <Card
                        v-for="item in props.items"
                        :key="item.key"
                        class="py-0"
                    >
                        <button
                            type="button"
                            class="w-full p-4 text-left focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            @click="emit('open', item)"
                        >
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{
                                    item.word
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
                                    {{ seenLabel(item) }}
                                </Badge>
                            </span>

                            <span
                                v-if="item.meaning"
                                class="mt-1 block text-sm text-muted-foreground"
                            >
                                {{ item.meaning }}
                            </span>
                            <span
                                v-else
                                class="mt-1 block text-sm text-muted-foreground italic"
                            >
                                Add the meaning
                            </span>

                            <span
                                v-if="item.example"
                                class="mt-1 block text-sm text-muted-foreground italic"
                            >
                                “{{ item.example }}”
                            </span>
                        </button>
                    </Card>
                </div>

                <div
                    v-if="props.loading"
                    class="mt-3 grid gap-3"
                    aria-busy="true"
                    aria-label="Loading more words"
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
                description="Finish a practice session and the words you save will be gathered here automatically."
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
                title="No words yet"
                description="Words you save while practicing will appear here, with their meanings and examples."
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
                        Your saved words are still here. Check your connection
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
                {{ props.loading ? 'Loading…' : 'Load more words' }}
            </Button>

            <p v-else class="text-xs text-muted-foreground">
                That is every word — {{ props.total }} in total.
            </p>

            <p v-if="props.hasMore" class="text-xs text-muted-foreground">
                Showing {{ props.items.length }} of {{ props.total }}
            </p>
        </div>
    </div>
</template>
