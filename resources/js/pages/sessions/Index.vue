<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PlusIcon } from '@lucide/vue';
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
    loading,
    error,
    loadMore,
    refresh,
    restore,
} = useLibrarySessions(props);

const description = computed(() => {
    if (total.value === 0) {
        return 'Every session you save will appear here, newest first.';
    }

    return `${total.value} ${
        total.value === 1 ? 'session' : 'sessions'
    }, newest first.`;
});

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

        <LibrarySessionList
            :sessions="sessions"
            :has-more="hasMore"
            :loading="loading"
            :error="error"
            :total="total"
            @load-more="loadMore"
        />
    </div>
</template>
