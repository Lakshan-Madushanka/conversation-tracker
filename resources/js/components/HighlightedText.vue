<script setup lang="ts">
import type { SearchSegment } from '@/types';

/**
 * Plain words with the parts that matched marked. The server sends the pieces
 * as plain text, so nothing here is ever treated as markup. A mark is also
 * underlined and bold, so the match never relies on colour alone.
 */
defineProps<{
    segments: SearchSegment[];
}>();
</script>

<template>
    <span>
        <template v-for="(segment, index) in segments" :key="index">
            <mark
                v-if="segment.match"
                class="rounded-sm bg-amber-200/80 px-0.5 font-semibold text-foreground underline decoration-amber-600 decoration-2 underline-offset-2 dark:bg-amber-400/30 dark:decoration-amber-400"
                >{{ segment.text }}</mark
            >
            <template v-else>{{ segment.text }}</template>
        </template>
    </span>
</template>
