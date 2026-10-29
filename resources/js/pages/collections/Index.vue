<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { InfoIcon, LayersIcon } from '@lucide/vue';
import CollectionItemPanel from '@/components/CollectionItemPanel.vue';
import CollectionList from '@/components/CollectionList.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCollection } from '@/composables/useCollection';
import AppLayout from '@/layouts/AppLayout.vue';
import { collectionTabConfig } from '@/lib/collection-tabs';
import { readCollectionQuery } from '@/lib/collection-query';
import {
    consumeCollectionRestore,
    isCollectionPage,
    readCollectionSnapshot,
    saveCollectionSnapshot,
    type CollectionSnapshot,
} from '@/lib/collections';
import { index as collectionsIndex } from '@/routes/collections';
import type { CollectionPage, CollectionTabKey } from '@/types';

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

/** The tab on screen, with its label, count and built flag. */
const activeTab = computed(
    () => props.tabs.find((candidate) => candidate.key === tab.value) ?? null,
);

/** Only My mistakes is still on its way; the rest are real collections. */
const isBuilt = computed(() => activeTab.value?.built === true);

const activeConfig = computed(() => collectionTabConfig(tab.value));

/** The tab's full name, with its count read as part of it. */
function tabAriaName(
    label: string,
    key: CollectionTabKey,
    count: number | null,
): string {
    if (count === null) {
        return label;
    }

    const config = collectionTabConfig(key);

    return `${label}, ${count} ${count === 1 ? config.unit : config.unitPlural}`;
}

const activeLabel = computed(() => activeTab.value?.label ?? null);

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
                    <span v-if="candidate.count !== null" class="truncate">
                        {{ candidate.label }} ({{ candidate.count }})
                    </span>
                    <span v-else>{{ candidate.label }}</span>
                </TabsTrigger>
            </TabsList>

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
                    v-if="isBuilt"
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
                />

                <!-- My mistakes stays visible and calm, so the page never
                    looks broken while its collection is on its way. -->
                <EmptyState
                    v-else
                    :icon="LayersIcon"
                    :title="`${activeLabel} are on their way`"
                    description="This collection is not ready yet. Vocabulary, phrases and grammar notes are gathered from your finished sessions."
                >
                    <template #actions>
                        <Button
                            type="button"
                            size="lg"
                            variant="outline"
                            @click="changeTab('vocabulary')"
                        >
                            Browse vocabulary
                        </Button>
                    </template>
                </EmptyState>
            </div>
        </Tabs>

        <CollectionItemPanel
            :item="openItem"
            :tab="tab"
            @close="closeItemPanel"
            @see-session="rememberForReturn"
        />
    </div>
</template>
