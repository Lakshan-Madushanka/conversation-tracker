<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import {
    libraryReturnUrl,
    markLibraryRestore,
    readLibrarySnapshot,
} from '@/lib/library';
import type { LibraryView } from '@/lib/library-filters';

const props = defineProps<{
    /** The session is archived, used when no saved list says otherwise. */
    archived?: boolean;
}>();

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
const snapshot = readLibrarySnapshot();

/** Which list the learner came from, so the way back says the same thing. */
const originView: LibraryView =
    snapshot?.filters.view ??
    (props.archived === true ? 'archived' : 'library');

const label = computed(() =>
    originView === 'archived' ? 'Back to Archived' : 'Back to Library',
);

const href = computed(() => libraryReturnUrl(originView === 'archived'));
</script>

<template>
    <Link
        :href="href"
        class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
        @click="rememberLibrary"
    >
        <ArrowLeftIcon class="size-4" aria-hidden="true" />
        {{ label }}
    </Link>
</template>
