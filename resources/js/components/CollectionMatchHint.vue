<script setup lang="ts">
import type { CollectionMatch } from '@/types';

/**
 * Where a search found an item, cut by the server into plain and matched
 * pieces. Every piece is text the learner wrote, so the highlight never
 * carries saved markup.
 */
defineProps<{ match: CollectionMatch }>();
</script>

<template>
    <span class="mt-1 block text-xs text-muted-foreground">
        <span class="sr-only">Matched in {{ match.label }}: </span>
        <span aria-hidden="true">{{ match.label }}: </span>
        <span
            v-for="(segment, index) in match.segments"
            :key="index"
            :class="
                segment.match
                    ? 'rounded-sm bg-primary/15 px-0.5 font-medium text-foreground'
                    : ''
            "
            >{{ segment.text }}</span
        >
    </span>
</template>
