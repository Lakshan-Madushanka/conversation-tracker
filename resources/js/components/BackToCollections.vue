<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { collectionReturnUrl, markCollectionRestore } from '@/lib/collections';

/**
 * A link back to the collection a word was opened from, bringing the loaded
 * list, the scroll position and the open word with it. Modified clicks (a new
 * tab, a download) leave the saved list alone, because they do not leave this
 * page.
 */
function rememberCollection(event: MouseEvent): void {
    if (
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey ||
        event.button !== 0
    ) {
        return;
    }

    markCollectionRestore();
}
</script>

<template>
    <Link
        :href="collectionReturnUrl()"
        class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
        @click="rememberCollection"
    >
        <ArrowLeftIcon class="size-4" aria-hidden="true" />
        Back to Collections
    </Link>
</template>
