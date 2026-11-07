<script setup lang="ts">
import ActiveFilterChips from '@/components/ActiveFilterChips.vue';
import {
    libraryFilterChips,
    type LibraryFilterChip,
    type LibraryFilters,
} from '@/lib/library-filters';
import type { TopicSummary } from '@/types';

const props = defineProps<{
    filters: LibraryFilters;
    topics: TopicSummary[];
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (event: 'remove', chip: LibraryFilterChip): void;
    (event: 'clear'): void;
    (event: 'focus-fallback'): void;
}>();

/** The library's own chip shape carries the value its removal needs. */
function remove(chip: { key: string; label: string }): void {
    emit('remove', chip as LibraryFilterChip);
}
</script>

<template>
    <ActiveFilterChips
        :chips="libraryFilterChips(props.filters, props.topics)"
        :disabled="props.disabled"
        @remove="remove"
        @clear="emit('clear')"
        @focus-fallback="emit('focus-fallback')"
    />
</template>
