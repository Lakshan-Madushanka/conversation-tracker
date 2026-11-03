<script setup lang="ts">
import { StarIcon } from '@lucide/vue';
import { Toggle } from '@/components/ui/toggle';

/**
 * The star on one collection item. It asks for the state the learner wants —
 * on or off — never toggles what it saw, so a retry or a second tab cannot
 * flip the outcome. The name includes the item, so a screen reader never
 * says just "star".
 */
const props = defineProps<{
    /** Whether the star is on right now. */
    starred: boolean;
    /** The accessible name, with the item in it. */
    label: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    /** The state the learner asked for. */
    change: [starred: boolean];
}>();
</script>

<template>
    <Toggle
        :model-value="props.starred"
        :disabled="props.disabled"
        :aria-label="props.label"
        class="size-8 shrink-0 cursor-pointer"
        @update:model-value="emit('change', Boolean($event))"
    >
        <StarIcon
            aria-hidden="true"
            :class="[
                'size-4',
                props.starred
                    ? 'fill-amber-500 text-amber-600 dark:fill-amber-400 dark:text-amber-300'
                    : 'text-muted-foreground',
            ]"
        />
    </Toggle>
</template>
