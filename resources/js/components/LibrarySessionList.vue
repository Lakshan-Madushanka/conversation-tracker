<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ListFilterIcon,
    LoaderCircleIcon,
    PlusIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import SessionCard from '@/components/SessionCard.vue';
import SessionCardSkeleton from '@/components/SessionCardSkeleton.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { create as sessionsCreate } from '@/routes/sessions';
import type { SessionCardData } from '@/types';

/** The cards loaded so far, with the states that explain how loading is going. */
const props = withDefaults(
    defineProps<{
        sessions: SessionCardData[];
        hasMore?: boolean;
        loading?: boolean;
        error?: boolean;
        total?: number;
        allTotal?: number;
        hasFilters?: boolean;
        /** True when a search is narrowing the list. */
        hasSearch?: boolean;
        /** True when any filter chip (not the search) is narrowing the list. */
        hasFilterChips?: boolean;
        /** True while a new filter state is on its way. */
        updating?: boolean;
        /** True when that filter request failed. */
        filterError?: boolean;
        /** True when the Archived view is on screen. */
        archivedView?: boolean;
    }>(),
    {
        hasMore: false,
        loading: false,
        error: false,
        total: 0,
        allTotal: 0,
        hasFilters: false,
        hasSearch: false,
        hasFilterChips: false,
        updating: false,
        filterError: false,
        archivedView: false,
    },
);

const emit = defineEmits<{
    (event: 'load-more'): void;
    (event: 'clear-filters'): void;
    (event: 'clear-search'): void;
    (event: 'retry-filters'): void;
    (event: 'archive', session: SessionCardData): void;
    (event: 'restore', session: SessionCardData): void;
}>();

/** A short row of placeholders while the next page arrives. */
const skeletonCount = 3;

/** What the no-results state says, depending on what is narrowing the list. */
const emptyTitle = computed(() =>
    props.hasSearch
        ? 'No sessions match your search'
        : 'No sessions match these filters',
);

const emptyDescription = computed(() => {
    if (props.hasSearch) {
        return 'Try different words, or clear the search to see everything again.';
    }

    return props.archivedView
        ? 'Try a different topic, date or content type. Your other archived sessions are still here.'
        : 'Try a different topic, date, content type or status. Your other sessions are still here.';
});

/** A "Clear all filters" button is only useful when a chip is on. */
const showClearFilters = computed(
    () => props.hasFilterChips || (!props.hasSearch && props.hasFilters),
);

/**
 * What a screen reader hears once a search or filter has settled. It stays
 * empty while a request is on its way, so typing a word announces one result
 * count rather than a stream of them.
 */
const resultAnnouncement = computed(() => {
    if (props.updating || props.loading) {
        return '';
    }

    if (props.hasFilters && props.total === 0) {
        return 'No sessions match.';
    }

    const counted = props.hasFilters
        ? `${props.total} ${props.total === 1 ? 'session matches' : 'sessions match'}.`
        : '';

    if (props.hasMore) {
        return `${counted} Showing ${props.sessions.length} of ${props.total}.`.trim();
    }

    return counted;
});

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

/**
 * Watches the end of the list, so scrolling near it starts the next page.
 * The "Load more" button stays available for anyone who prefers it, and for
 * browsers without IntersectionObserver.
 */
function observeSentinel(): void {
    observer?.disconnect();
    observer = null;

    if (
        sentinel.value === null ||
        typeof IntersectionObserver === 'undefined'
    ) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            if (
                !props.loading &&
                !props.updating &&
                entries.some((entry) => entry.isIntersecting)
            ) {
                emit('load-more');
            }
        },
        { rootMargin: '400px 0px' },
    );

    observer.observe(sentinel.value);
}

onMounted(observeSentinel);

watch(
    () => props.hasMore,
    async () => {
        await nextTick();
        observeSentinel();
    },
);

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div>
        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ resultAnnouncement }}
        </p>

        <div
            v-if="props.updating"
            class="grid gap-3"
            aria-busy="true"
            aria-label="Updating the list"
        >
            <SessionCardSkeleton
                v-for="skeleton in skeletonCount"
                :key="skeleton"
            />
        </div>

        <template v-else>
            <div
                v-if="props.sessions.length > 0 || props.loading"
                class="grid gap-3"
                :aria-busy="props.loading"
            >
                <SessionCard
                    v-for="session in props.sessions"
                    :key="session.id"
                    :session="session"
                    @archive="emit('archive', $event)"
                    @restore="emit('restore', $event)"
                />

                <SessionCardSkeleton
                    v-for="skeleton in props.loading ? skeletonCount : 0"
                    :key="`skeleton-${skeleton}`"
                />
            </div>

            <EmptyState
                v-else-if="
                    props.archivedView &&
                    props.allTotal === 0 &&
                    !props.loading &&
                    !props.error &&
                    !props.filterError
                "
                title="No archived sessions"
                description="Finished sessions you archive will appear here. You can restore one at any time."
            />

            <EmptyState
                v-else-if="
                    props.allTotal === 0 &&
                    !props.loading &&
                    !props.error &&
                    !props.filterError
                "
                title="No sessions yet"
                description="Start your first practice session and it will show up here."
            >
                <template #actions>
                    <Button size="lg" as-child>
                        <Link :href="sessionsCreate.url()">
                            <PlusIcon aria-hidden="true" />
                            Start your first session
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="
                    props.sessions.length === 0 &&
                    props.hasFilters &&
                    !props.error &&
                    !props.filterError
                "
                :icon="ListFilterIcon"
                :title="emptyTitle"
                :description="emptyDescription"
            >
                <template #actions>
                    <div
                        class="flex flex-wrap items-center justify-center gap-2"
                    >
                        <Button
                            v-if="props.hasSearch"
                            type="button"
                            variant="outline"
                            class="min-h-11 md:min-h-9"
                            @click="emit('clear-search')"
                        >
                            Clear search
                        </Button>

                        <Button
                            v-if="showClearFilters"
                            type="button"
                            variant="outline"
                            class="min-h-11 md:min-h-9"
                            @click="emit('clear-filters')"
                        >
                            Clear all filters
                        </Button>
                    </div>
                </template>
            </EmptyState>

            <Alert v-if="props.filterError" variant="destructive" class="mt-4">
                <TriangleAlertIcon aria-hidden="true" />
                <AlertTitle>Could not update the list</AlertTitle>
                <AlertDescription>
                    <p>
                        Your saved sessions are still here. Check your
                        connection and try again.
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

            <Alert v-if="props.error" variant="destructive" class="mt-4">
                <TriangleAlertIcon aria-hidden="true" />
                <AlertTitle>Could not load more sessions</AlertTitle>
                <AlertDescription>
                    <p>
                        Your saved sessions are still here. Check your
                        connection and try again.
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
            v-if="props.sessions.length > 0 && !props.updating"
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
                {{ props.loading ? 'Loading…' : 'Load more sessions' }}
            </Button>

            <p v-else class="text-xs text-muted-foreground">
                {{
                    props.hasFilters
                        ? `That is every matching session — ${props.total} in total.`
                        : props.archivedView
                          ? `That is every archived session — ${props.total} in total.`
                          : `That is every session — ${props.total} in total.`
                }}
            </p>

            <p v-if="props.hasMore" class="text-xs text-muted-foreground">
                Showing {{ props.sessions.length }} of {{ props.total }}
            </p>
        </div>

        <div
            v-if="props.hasMore && !props.updating"
            ref="sentinel"
            class="h-px"
            aria-hidden="true"
        />
    </div>
</template>
