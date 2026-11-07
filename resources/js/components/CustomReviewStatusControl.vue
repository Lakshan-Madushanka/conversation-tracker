<script setup lang="ts">
import { computed } from 'vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { CustomReviewStatusValue } from '@/types';

/**
 * The one control that moves a review between Draft and Finished.
 *
 * Only the learner moves it, so this never guesses a status from the item
 * count. An Empty review is still shown here, with both choices unavailable,
 * because the page explains what is missing rather than hiding the control.
 */
const props = defineProps<{
    status: CustomReviewStatusValue;
    /** A review with nothing in it cannot be marked finished. */
    canFinish: boolean;
    busy?: boolean;
}>();

const emit = defineEmits<{
    (event: 'change', status: 'draft' | 'finished'): void;
}>();

const chosen = computed(() =>
    props.status === 'empty' ? undefined : props.status,
);

const empty = computed(() => props.status === 'empty');

function choose(value: unknown): void {
    if (value !== 'draft' && value !== 'finished') {
        return;
    }

    if (value === props.status) {
        return;
    }

    emit('change', value);
}
</script>

<template>
    <ToggleGroup
        type="single"
        variant="outline"
        :model-value="chosen"
        :disabled="props.busy"
        aria-label="Status"
        class="h-auto w-full justify-start sm:w-auto"
        @update:model-value="choose"
    >
        <ToggleGroupItem
            value="draft"
            :disabled="empty"
            class="min-h-11 px-4 md:min-h-9"
        >
            Draft
        </ToggleGroupItem>
        <ToggleGroupItem
            value="finished"
            :disabled="!props.canFinish"
            class="min-h-11 px-4 md:min-h-9"
        >
            Finished
        </ToggleGroupItem>
    </ToggleGroup>
</template>
