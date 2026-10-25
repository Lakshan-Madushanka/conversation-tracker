<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { markLibraryRestore } from '@/lib/library';
import { index as sessionsIndex } from '@/routes/sessions';

/**
 * A link back to the Library that brings the learner's loaded list and scroll
 * position with them. Modified clicks (a new tab, a download) leave the list
 * alone, because they do not leave this page.
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
</script>

<template>
    <Link
        :href="sessionsIndex.url()"
        class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
        @click="rememberLibrary"
    >
        <ArrowLeftIcon class="size-4" aria-hidden="true" />
        Back to Library
    </Link>
</template>
