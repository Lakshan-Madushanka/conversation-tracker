<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PlusIcon } from '@lucide/vue';
import LibraryFilterBar from '@/components/LibraryFilterBar.vue';
import LibrarySearch from '@/components/LibrarySearch.vue';
import LibrarySessionList from '@/components/LibrarySessionList.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useLibrarySessions } from '@/composables/useLibrarySessions';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    consumeLibraryRestore,
    readLibrarySnapshot,
    saveLibrarySnapshot,
    type LibraryPage,
    type LibrarySnapshot,
} from '@/lib/library';
import {
    activeFilterCount,
    hasActiveFilters,
    hasSearch,
    sessionCountLabel,
    sortLabel,
    type LibraryFilters,
} from '@/lib/library-filters';
import {
    create as sessionsCreate,
    index as sessionsIndex,
} from '@/routes/sessions';

defineOptions({ layout: AppLayout });

const props = defineProps<LibraryPage>();

const {
    sessions,
    nextCursor,
    hasMore,
    total,
    allTotal,
    filters,
    topics,
    timezone,
    loading,
    error,
    updating,
    filterError,
    loadMore,
    refresh,
    restore,
    applyFilters,
    retryFilters,
    clearFilters,
    setSearch,
    clearSearch,
} = useLibrarySessions(props);

const filterBar = ref<InstanceType<typeof LibraryFilterBar> | null>(null);
const searchBox = ref<InstanceType<typeof LibrarySearch> | null>(null);

const filtered = computed(() => hasActiveFilters(filters.value));

/** The filter chips in force, without counting the search. */
const hasFilterChips = computed(() => activeFilterCount(filters.value) > 0);

const searchActive = computed(() => hasSearch(filters.value));

const count = computed(() =>
    sessionCountLabel(total.value, allTotal.value, filtered.value),
);

const description = computed(() => {
    if (allTotal.value === 0) {
        return 'Every session you save will appear here, newest first.';
    }

    return `${count.value}, ${sortLabel(filters.value.sort)}.`;
});

/** A quiet announcement after the list has settled, never on every change. */
const announcement = ref('');

watch(updating, (value, oldValue) => {
    if (value || !oldValue || filterError.value) {
        return;
    }

    announcement.value = `${count.value}.`;
});

function changeFilters(next: LibraryFilters): void {
    applyFilters(next);
}

function clearFromEmptyState(): void {
    clearFilters();
    filterBar.value?.focusFirstControl();
}

/** Search as the learner types; only the latest request may change the list. */
function changeSearch(search: string): void {
    setSearch(search);
}

/** Clear the words, keeping the filters, and put the cursor back in the box. */
function clearSearchFromEmptyState(): void {
    clearSearch();
    searchBox.value?.focus();
}

/** The last scroll position read, kept for the trip back from a session. */
let lastScrollY = 0;

function rememberScroll(): void {
    lastScrollY = window.scrollY;
}

/**
 * Puts the saved list back and returns to the same place on the page. The
 * scroll runs a moment later, after Inertia has finished its own scroll reset.
 */
function restoreSnapshot(snapshot: LibrarySnapshot): void {
    // An empty saved list would only hide the fresh page the server just sent,
    // so let that page show instead.
    if (snapshot.sessions.length === 0) {
        return;
    }

    restore(snapshot);
    void refresh();

    window.setTimeout(() => {
        window.scrollTo(0, snapshot.scrollY);
    }, 0);
}

let stopListeningToHistory: (() => void) | null = null;

onMounted(() => {
    // "Back to Library" marks the visit, so the list comes back instantly.
    if (consumeLibraryRestore()) {
        const snapshot = readLibrarySnapshot();

        if (snapshot !== null) {
            restoreSnapshot(snapshot);
        }
    }

    // The browser's Back button is a history visit. Restore the list the
    // learner had, even though the page props only hold the first page.
    stopListeningToHistory = router.on('navigate', (event) => {
        if (event.detail.type !== 'history') {
            return;
        }

        const path = new URL(event.detail.page.url, window.location.origin)
            .pathname;

        if (path !== sessionsIndex.url()) {
            return;
        }

        const snapshot = readLibrarySnapshot();

        if (snapshot !== null) {
            restoreSnapshot(snapshot);
        }
    });

    rememberScroll();
    window.addEventListener('scroll', rememberScroll, { passive: true });
});

onBeforeUnmount(() => {
    saveLibrarySnapshot({
        sessions: sessions.value,
        nextCursor: nextCursor.value,
        hasMore: hasMore.value,
        total: total.value,
        allTotal: allTotal.value,
        filters: filters.value,
        timezone: timezone.value,
        scrollY: lastScrollY,
        savedAt: Date.now(),
    });

    stopListeningToHistory?.();
    window.removeEventListener('scroll', rememberScroll);
});
</script>

<template>
    <div>
        <Head title="Library" />

        <PageHeader title="Library" :description="description">
            <template #actions>
                <Button size="lg" as-child>
                    <Link :href="sessionsCreate.url()">
                        <PlusIcon aria-hidden="true" />
                        New session
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <p class="sr-only" aria-live="polite" aria-atomic="true">
            {{ announcement }}
        </p>

        <LibrarySearch
            ref="searchBox"
            :model-value="filters.q"
            :updating="updating"
            @search="changeSearch"
        />

        <LibraryFilterBar
            ref="filterBar"
            :filters="filters"
            :topics="topics"
            :updating="updating"
            :disabled="updating"
            @change="changeFilters"
            @clear="clearFilters"
        />

        <LibrarySessionList
            :sessions="sessions"
            :has-more="hasMore"
            :loading="loading"
            :error="error"
            :total="total"
            :all-total="allTotal"
            :has-filters="filtered"
            :has-search="searchActive"
            :has-filter-chips="hasFilterChips"
            :updating="updating"
            :filter-error="filterError"
            @load-more="loadMore"
            @clear-filters="clearFromEmptyState"
            @clear-search="clearSearchFromEmptyState"
            @retry-filters="retryFilters"
        />
    </div>
</template>
