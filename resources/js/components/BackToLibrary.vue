<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { filtersToQuery } from '@/lib/library-filters';
import { markLibraryRestore, readLibrarySnapshot } from '@/lib/library';
import { index as sessionsIndex } from '@/routes/sessions';

/**
 * A link back to the Library that brings the learner's loaded list, filters
 * and scroll position with them. Modified clicks (a new tab, a download) leave
 * the list alone, because they do not leave this page.
 */
function rememberLibrary(event: MouseEvent): void {
    if (
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey ||
        event.button !== 0
    ) {
        return;
    }

    markLibraryRestore();
}

/**
 * The saved filters make the address match the list that is about to be put
 * back, so a refresh or a bookmark after returning keeps the same view.
 */
const href = computed(() => {
    const snapshot = readLibrarySnapshot();

    if (snapshot === null) {
        return sessionsIndex.url();
    }

    return sessionsIndex.url({
        query: filtersToQuery(snapshot.filters, snapshot.timezone),
    });
});
</script>

<template>
    <Link
        :href="href"
        class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
        @click="rememberLibrary"
    >
        <ArrowLeftIcon class="size-4" aria-hidden="true" />
        Back to Library
    </Link>
</template>
