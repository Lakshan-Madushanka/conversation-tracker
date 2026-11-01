<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { InfoIcon } from '@lucide/vue';
import CollectionItemPanel from '@/components/CollectionItemPanel.vue';
import CollectionList from '@/components/CollectionList.vue';
import CollectionMistakePanel from '@/components/CollectionMistakePanel.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCollection } from '@/composables/useCollection';
import AppLayout from '@/layouts/AppLayout.vue';
import { collectionTabConfig } from '@/lib/collection-tabs';
import { toast } from 'vue-sonner';
import { markKindForTab, saveItemMark } from '@/lib/collection-marks';
import { readCollectionQuery } from '@/lib/collection-query';
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
    items,
    nextCursor,
    hasMore,
    total,
    hasFinishedSessions,
    openItem,
    openItemMissing,
    loading,
    error,
    updating,
    loadMore,
    refresh,
    setTab,
    openItemPanel,
    closeItemPanel,
    dismissMissingItem,
    rememberForReturn,
    restore,
    syncFromPage,
} = useCollection(props);

const activeConfig = computed(() => collectionTabConfig(tab.value));

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

/** A screen reader hears this even when stillness hides the failure. */
const markNotice = ref('');

function markFailed(message: string): void {
    markNotice.value = message;
    toast.error(message);
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
    } catch {
        applyMark(item.key, { status: item.status });
        markFailed(
            'The status was not saved. Nothing changed; check your connection and try again.',
        );
    }
}

/** A mark saved from the open panel updates the list as well. */
function onMarkSaved(item: CollectionOpenedItem, mark: ItemMarkData): void {
    applyMark(item.key, mark);
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

    return (
        snapshot.tab === target.tab &&
        snapshot.openItemKey === target.item &&
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
    saveCollectionSnapshot({
        tab: tab.value,
        items: [...items.value],
        nextCursor: nextCursor.value,
        hasMore: hasMore.value,
        total: total.value,
        openItemKey: openItem.value?.key ?? null,
        scrollY: lastScrollY,
        savedAt: Date.now(),
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
            <TabsList class="grid w-full grid-cols-4" aria-label="Collections">
                <TabsTrigger
                    v-for="candidate in props.tabs"
                    :key="candidate.key"
                    class="cursor-pointer"
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
                    <span class="truncate">
                        {{ candidate.label }} ({{ candidate.count }})
                    </span>
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
                        @click="dismissMissingItem"
                    >
                        Dismiss
                    </Button>
                </AlertDescription>
            </Alert>

            <div class="mt-4">
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
                    :has-finished-sessions="hasFinishedSessions"
                    @open="openItemPanel"
                    @load-more="loadMore"
                    @star="changeStar"
                    @status="changeStatus"
                />
            </div>
        </Tabs>

        <CollectionMistakePanel
            v-if="tab === 'mistakes'"
            :item="openItem as CollectionMistakeItem | null"
            tab="mistakes"
            @close="closeItemPanel"
            @see-session="rememberForReturn"
            @mark-saved="onMarkSaved"
        />

        <CollectionItemPanel
            v-else
            :item="openItem"
            :tab="tab"
            @close="closeItemPanel"
            @see-session="rememberForReturn"
            @mark-saved="onMarkSaved"
        />
    </div>
</template>
