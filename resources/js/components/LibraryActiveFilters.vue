<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { XIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

const container = ref<HTMLElement | null>(null);

/**
 * Removing a chip leaves focus on the next one, or on Clear all when that was
 * the last filter. When nothing is left, the parent puts focus on the first
 * control so the learner is never dropped at the top of the page.
 */
function remove(chip: LibraryFilterChip, index: number): void {
    emit('remove', chip);

    void nextTick(() => {
        const buttons =
            container.value?.querySelectorAll<HTMLButtonElement>(
                '[data-chip-remove]',
            );

        const target =
            buttons?.[Math.min(index, (buttons?.length ?? 1) - 1)] ??
            container.value?.querySelector<HTMLButtonElement>(
                '[data-clear-filters]',
            );

        if (target) {
            target.focus();

            return;
        }

        emit('focus-fallback');
    });
}
</script>

<template>
    <div
        v-if="libraryFilterChips(props.filters, props.topics).length > 0"
        ref="container"
        class="flex flex-wrap items-center gap-2"
    >
        <Badge
            v-for="(chip, index) in libraryFilterChips(
                props.filters,
                props.topics,
            )"
            :key="chip.key"
            variant="outline"
            class="h-auto min-h-9 gap-1 rounded-full py-0 pr-1 pl-3 md:min-h-6"
        >
            <span class="text-xs font-medium">{{ chip.label }}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                class="relative size-7 rounded-full after:absolute after:-inset-2 after:content-[''] md:size-5 md:after:hidden"
                :disabled="props.disabled"
                :aria-label="`Remove ${chip.label} filter`"
                data-chip-remove
                @click="remove(chip, index)"
            >
                <XIcon aria-hidden="true" />
            </Button>
        </Badge>

        <Button
            type="button"
            variant="ghost"
            size="sm"
            class="min-h-11 md:min-h-7"
            :disabled="props.disabled"
            data-clear-filters
            @click="emit('clear')"
        >
            Clear all
        </Button>
    </div>
</template>
