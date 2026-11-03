<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { InfoIcon, TriangleAlertIcon } from '@lucide/vue';
import CollectionFilterBar from '@/components/CollectionFilterBar.vue';
import CollectionItemPanel from '@/components/CollectionItemPanel.vue';
import CollectionList from '@/components/CollectionList.vue';
import CollectionMistakePanel from '@/components/CollectionMistakePanel.vue';
import CollectionSearch from '@/components/CollectionSearch.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCollection } from '@/composables/useCollection';
import AppLayout from '@/layouts/AppLayout.vue';
import { collectionTabConfig } from '@/lib/collection-tabs';
import { toast } from 'vue-sonner';
import {
    attentionCountLabel,
    collectionActiveFilterCount,
    collectionCountLabel,
    hasCollectionFilters,
    hasCollectionSearch,
    normalizeCollectionFilters,
} from '@/lib/collection-filters';
import {
    markItemName,
    markKindForTab,
    saveItemMark,
} from '@/lib/collection-marks';
import {
    readCollectionFilters,
    readCollectionQuery,
} from '@/lib/collection-query';
import {
    consumeCollectionRestore,
    isCollectionPage,
    readCollectionSnapshot,
    saveCollectionSnapshot,
    type CollectionSnapshot,
} from '@/lib/collections';
import { index as collectionsIndex } from '@/routes/collections';
import type {
    CollectionItemStatusValue,
    CollectionMistakeItem,
    CollectionOpenedItem,
    CollectionPage,
    CollectionTabKey,
    ItemMarkData,
} from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<CollectionPage>();

const {
    tab,
    tabs,
    items,
    nextCursor,
    hasMore,
    total,
    allTotal,
    attentionCount,
    filters,
    topicOptions,
    hasFinishedSessions,
    openItem,
    openItemMissing,
    loading,
    error,
    filterError,
    updating,
    loadMore,
    refresh,
    setTab,
    applyFilters,
    retryFilters,
    setSearch,
    clearSearch,
    clearFilters,
    openItemPanel,
    closeItemPanel,
    dismissMissingItem,
    rememberForReturn,
    restore,
    syncFromPage,
} = useCollection(props);

const activeConfig = computed(() => collectionTabConfig(tab.value));

/** How much is shown, in the collection's own words. */
const countLabel = computed(() =>
    collectionCountLabel(
        total.value,
        allTotal.value,
        activeConfig.value.unit,
        activeConfig.value.unitPlural,
        hasCollectionFilters(filters.value),
    ),
);

const attentionLabel = computed(() =>
    attentionCountLabel(attentionCount.value),
);

const showAttention = computed(
    () => tab.value !== 'grammar' && attentionCount.value > 0,
);

/** A screen reader hears this even when stillness hides the failure. */
const markNotice = ref('');

/** A mark saved from the open panel waits here until the panel closes. */
const pendingPanelMark = ref<{ key: string; mark: ItemMarkData } | null>(null);

/**
 * A saved mark reaches every copy of the item on screen: the list and the
 * open panel use the same truth, however the mark was saved.
 */
function applyMark(itemKey: string, mark: Partial<ItemMarkData>): void {
    items.value = items.value.map((listed) =>
        listed.key === itemKey ? { ...listed, ...mark } : listed,
    );

    const open = openItem.value;

    if (open !== null && open.key === itemKey) {
        openItem.value = { ...open, ...mark };
    }
}

function markFailed(message: string): void {
    markNotice.value = message;
    toast.error(message);
}

/** Whether an item still belongs in the list under the current filters. */
function itemMatchesFilters(item: CollectionOpenedItem): boolean {
    const active = filters.value;

    if (active.starred && !item.starred) {
        return false;
    }

    if (active.status === 'none' && item.status !== null) {
        return false;
    }

    if (active.status === 'learned' && item.status !== 'learned') {
        return false;
    }

    if (
        active.status === 'still_difficult' &&
        item.status !== 'still_difficult'
    ) {
        return false;
    }

    // Grammar notes never ask for attention, so they stay when the choice is
    // on, exactly as the server keeps them.
    return !(
        active.attention &&
        'needs_attention' in item &&
        !item.needs_attention
    );
}

/**
 * Puts focus on one item's open button when it is on screen, and otherwise on
 * the search box, so the learner is never dropped at the top of the page.
 */
function focusCollectionItem(itemKey: string | null): void {
    void nextTick(() => {
        if (itemKey !== null) {
            const candidates = Array.from(
                document.querySelectorAll<HTMLElement>(
                    '[data-collection-open]',
                ),
            );
            const matches = candidates.filter(
                (button) => button.dataset.collectionOpen === itemKey,
            );
            const target =
                matches.find((button) => button.offsetParent !== null) ??
                matches[0];

            if (target) {
                target.focus();

                return;
            }
        }

        document.getElementById('collection-search')?.focus();
    });
}

/**
 * Puts focus on the item that took the removed one's place, then the one
 * before it, and finally the search box.
 */
function focusAfterRemoval(preferredKey: string | null): void {
    focusCollectionItem(preferredKey);
}

/**
 * Takes an item out of the list when a mark means it no longer matches the
 * filter it was found under, and says so.
 */
function reconcileItem(itemKey: string): void {
    const index = items.value.findIndex((item) => item.key === itemKey);
    const updated = items.value[index];

    if (index === -1 || updated === undefined) {
        return;
    }

    if (itemMatchesFilters(updated)) {
        return;
    }

    const neighbourKey =
        items.value[index + 1]?.key ?? items.value[index - 1]?.key ?? null;

    items.value = items.value.filter((item) => item.key !== itemKey);
    total.value = Math.max(0, total.value - 1);
    markNotice.value = `“${markItemName(updated)}” no longer matches the filters, so it was removed.`;
    focusAfterRemoval(neighbourKey);
}

/** Saves the state the learner asked for, and puts the old state back when the server refuses. */
async function changeStar(
    item: CollectionOpenedItem,
    starred: boolean,
): Promise<void> {
    if (item.starred === starred) {
        return;
    }

    applyMark(item.key, { starred });

    try {
        const mark = await saveItemMark(
            markKindForTab(tab.value),
            item.key,
            starred,
            item.status,
        );

        applyMark(item.key, mark);
        reconcileItem(item.key);
    } catch {
        applyMark(item.key, { starred: item.starred });
        markFailed(
            'The star was not saved. Nothing changed; check your connection and try again.',
        );
    }
}

/** Saves the status the learner asked for, and puts the old one back when the server refuses. */
async function changeStatus(
    item: CollectionOpenedItem,
    status: CollectionItemStatusValue | null,
): Promise<void> {
    if (item.status === status) {
        return;
    }

    const starred = item.starred;
    applyMark(item.key, { status });

    try {
        const mark = await saveItemMark(
            markKindForTab(tab.value),
            item.key,
            starred,
            status,
        );

        applyMark(item.key, mark);
        reconcileItem(item.key);
    } catch {
        applyMark(item.key, { status: item.status });
        markFailed(
            'The status was not saved. Nothing changed; check your connection and try again.',
        );
    }
}

/**
 * A mark saved from the open panel updates the panel at once, but the list
 * only when the panel closes, so the learner is not moved out from under a
 * panel they are still using.
 */
function onMarkSaved(item: CollectionOpenedItem, mark: ItemMarkData): void {
    const open = openItem.value;

    if (open !== null && open.key === item.key) {
        openItem.value = { ...open, ...mark };
    }

    pendingPanelMark.value = { key: item.key, mark };
}

/** Apply a mark the open panel left behind, if any. */
function flushPendingPanelMark(): void {
    const pending = pendingPanelMark.value;
    pendingPanelMark.value = null;

    if (pending === null) {
        return;
    }

    applyMark(pending.key, pending.mark);
    reconcileItem(pending.key);
}

/** Close the panel, then let its saved mark reach the list. */
function closePanel(): void {
    const itemKey = openItem.value?.key ?? null;

    closeItemPanel();
    flushPendingPanelMark();

    // A mark may have removed the item; in that case reconcileItem has already
    // moved focus to its neighbour. Otherwise the row it came from gets focus
    // back, so closing the panel never drops the learner at the top.
    if (itemKey !== null && items.value.some((item) => item.key === itemKey)) {
        focusCollectionItem(itemKey);
    }
}

/** Dismiss the calm note about an item that is no longer there. */
function dismissMissing(): void {
    dismissMissingItem();
    flushPendingPanelMark();
    focusCollectionItem(null);
}

const filterBar = ref<InstanceType<typeof CollectionFilterBar> | null>(null);

/** Clearing the search from the empty state should leave the box focused. */
function clearSearchAndFocus(): void {
    clearSearch();
    focusCollectionItem(null);
}

/** Clearing the filters from the empty state should leave a filter focused. */
function clearFiltersAndFocus(): void {
    clearFilters();
    filterBar.value?.focusFirstControl();
}

/** Show only the items that need attention, or the whole collection again. */
function toggleAttention(): void {
    applyFilters(
        normalizeCollectionFilters(
            { ...filters.value, attention: !filters.value.attention },
            tab.value,
        ),
    );
}

/** A short friendly line under the tabs, for the tabs that have one. */
const intro = computed(() => activeConfig.value.intro);

/** The tab's full name, with its count read as part of it. */
function tabAriaName(
    label: string,
    key: CollectionTabKey,
    count: number,
): string {
    if (count === 0) {
        return label;
    }

    const config = collectionTabConfig(key);

    return `${label}, ${count} ${count === 1 ? config.unit : config.unitPlural}`;
}

function changeTab(value: string | number): void {
    setTab(value as CollectionTabKey);
}

/** The last scroll position read, kept for the trip back from a session. */
let lastScrollY = 0;

function rememberScroll(): void {
    lastScrollY = window.scrollY;
}

/**
 * Puts the saved list back and returns to the same place on the page. An empty
 * saved list would only hide the fresh page the server sent, so that page is
 * left to show instead.
 */
function restoreSnapshot(
    snapshot: CollectionSnapshot,
    openItemKey: string | null = snapshot.openItemKey,
): void {
    if (snapshot.items.length === 0) {
        return;
    }

    restore(snapshot, openItemKey);
    void refresh();

    window.setTimeout(() => {
        window.scrollTo(0, snapshot.scrollY);
    }, 0);
}

/** Whether a saved list describes the address the browser just restored. */
function matchesSnapshot(snapshot: CollectionSnapshot, url: string): boolean {
    const target = readCollectionQuery(url);
    const savedFilters = normalizeCollectionFilters(
        snapshot.filters,
        snapshot.tab,
    );
    const targetFilters = readCollectionFilters(url, target.tab);

    return (
        snapshot.tab === target.tab &&
        snapshot.openItemKey === target.item &&
        JSON.stringify(savedFilters) === JSON.stringify(targetFilters) &&
        snapshot.items.length > 0
    );
}

let stopListeningToHistory: (() => void) | null = null;

onMounted(() => {
    // "Back to Collections" marks the visit, so the list comes back instantly.
    if (consumeCollectionRestore()) {
        const snapshot = readCollectionSnapshot();

        if (snapshot !== null) {
            restoreSnapshot(
                snapshot,
                readCollectionQuery(window.location.href).item,
            );
        }
    }

    // A browser Back within Collections is a history visit: close the panel or
    // follow the tab the address names without asking the server again.
    stopListeningToHistory = router.on('navigate', (event) => {
        if (event.detail.type !== 'history') {
            return;
        }

        const url = event.detail.page.url;

        if (
            new URL(url, window.location.origin).pathname !==
            collectionsIndex.url()
        ) {
            return;
        }

        const target = readCollectionQuery(url);
        const snapshot = readCollectionSnapshot();

        // Leaving the panel through history is still closing it, so a mark
        // saved there reaches the list now.
        flushPendingPanelMark();

        if (snapshot !== null && matchesSnapshot(snapshot, url)) {
            restoreSnapshot(snapshot, target.item);

            return;
        }

        if (isCollectionPage(event.detail.page.props)) {
            syncFromPage(event.detail.page.props, target.item);
        }
    });

    rememberScroll();
    window.addEventListener('scroll', rememberScroll, { passive: true });
});

onBeforeUnmount(() => {
    flushPendingPanelMark();

    saveCollectionSnapshot({
        tab: tab.value,
        items: [...items.value],
        nextCursor: nextCursor.value,
        hasMore: hasMore.value,
        total: total.value,
        openItemKey: openItem.value?.key ?? null,
        scrollY: lastScrollY,
        savedAt: Date.now(),
        filters: { ...filters.value, topics: [...filters.value.topics] },
        topicOptions: [...topicOptions.value],
        allTotal: allTotal.value,
        attentionCount: attentionCount.value,
    });

    stopListeningToHistory?.();
    window.removeEventListener('scroll', rememberScroll);
});
</script>

<template>
    <div>
        <Head title="Collections" />

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ markNotice }}
        </p>

        <PageHeader
            title="Collections"
            description="Vocabulary, phrases, grammar notes and your common mistakes, gathered from every finished session."
        />

        <Tabs :model-value="tab" @update:model-value="changeTab">
            <!-- A phone scrolls the four tabs sideways rather than squeezing
                the names until they are cut off. -->
            <TabsList
                class="flex min-h-11 w-full justify-start overflow-x-auto md:grid md:grid-cols-4"
                aria-label="Collections"
            >
                <TabsTrigger
                    v-for="candidate in tabs"
                    :key="candidate.key"
                    class="min-h-11     +flex-none cursor-pointer px-3 md:min-h-0 md:flex-1"
                    :value="candidate.key"
                    :aria-label="
                        tabAriaName(
                            candidate.label,
                            candidate.key,
                            candidate.count,
                        )
                    "
                >
                    <!-- Each count is read as part of the tab's name, so a
                        screen reader hears "Phrases, 65 phrases". -->
                    <span> {{ candidate.label }} ({{ candidate.count }}) </span>
                </TabsTrigger>
            </TabsList>

            <p v-if="intro" class="mt-4 text-sm text-muted-foreground">
                {{ intro }}
            </p>

            <Alert v-if="openItemMissing" class="mt-4" role="status">
                <InfoIcon aria-hidden="true" />
                <AlertTitle>{{ activeConfig.missingTitle }}</AlertTitle>
                <AlertDescription>
                    <p>{{ activeConfig.missingDescription }}</p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="dismissMissing"
                    >
                        Dismiss
                    </Button>
                </AlertDescription>
            </Alert>

            <div class="mt-4">
                <!-- Search sits first, then the chips and controls that
                    narrow the same collection. -->
                <CollectionSearch
                    :model-value="filters.q"
                    :updating="updating"
                    @search="setSearch"
                />

                <CollectionFilterBar
                    ref="filterBar"
                    :filters="filters"
                    :topics="topicOptions"
                    :tab="tab"
                    :attention-count="attentionCount"
                    :updating="updating"
                    @change="applyFilters"
                    @clear="clearFilters"
                />

                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-2"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ countLabel }}
                    </p>

                    <Button
                        v-if="showAttention"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="min-h-11 md:min-h-8"
                        :aria-pressed="filters.attention"
                        @click="toggleAttention"
                    >
                        <TriangleAlertIcon aria-hidden="true" />
                        {{ attentionLabel }}
                    </Button>
                </div>

                <!-- The gathered collections: list, loading, empty and error
                    states live in the list itself. -->
                <CollectionList
                    :items="items"
                    :tab="tab"
                    :has-more="hasMore"
                    :loading="loading"
                    :error="error"
                    :updating="updating"
                    :total="total"
                    :all-total="allTotal"
                    :has-filters="collectionActiveFilterCount(filters) > 0"
                    :has-search="hasCollectionSearch(filters)"
                    :filter-error="filterError"
                    :has-finished-sessions="hasFinishedSessions"
                    @open="openItemPanel"
                    @load-more="loadMore"
                    @star="changeStar"
                    @status="changeStatus"
                    @clear-search="clearSearchAndFocus"
                    @clear-filters="clearFiltersAndFocus"
                    @retry-filters="retryFilters"
                />
            </div>
        </Tabs>

        <CollectionMistakePanel
            v-if="tab === 'mistakes'"
            :item="openItem as CollectionMistakeItem | null"
            tab="mistakes"
            @close="closePanel"
            @see-session="rememberForReturn"
            @mark-saved="onMarkSaved"
        />

        <CollectionItemPanel
            v-else
            :item="openItem"
            :tab="tab"
            @close="closePanel"
            @see-session="rememberForReturn"
            @mark-saved="onMarkSaved"
        />
    </div>
</template>
