<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    CircleCheckIcon,
    CircleHelpIcon,
    LoaderCircleIcon,
    RefreshCwIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import type { AutoSaveStatus } from '@/composables/useAutoSave';

const props = defineProps<{
    status: AutoSaveStatus;
    lastSavedAt: number | null;
}>();

/** The text next to the icon. Kept short so the top bar does not jump. */
const label = computed(() => {
    switch (props.status) {
        case 'needs_topic':
            return 'Choose a topic to start saving';
        case 'saving':
            return 'Saving…';
        case 'saved':
            return 'Saved';
        case 'retrying':
            return 'Not saved. Retrying…';
        case 'conflict':
            return 'Not saved — changed elsewhere';
        default:
            return 'Not saved';
    }
});

/** A longer description for the tooltip and for screen readers. */
const description = computed(() => {
    if (props.status === 'saved' && props.lastSavedAt) {
        return `Saved at ${new Date(props.lastSavedAt).toLocaleTimeString()}`;
    }

    return label.value;
});

const tone = computed(() => {
    switch (props.status) {
        case 'saved':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'saving':
            return 'text-muted-foreground';
        case 'needs_topic':
            return 'text-muted-foreground';
        default:
            return 'text-amber-600 dark:text-amber-400';
    }
});

/**
 * Only meaningful changes are announced. Routine Saving… / Saved flips are
 * silent so the indicator does not chatter; a failure and the recovery from it
 * are spoken once.
 */
const announcement = ref('');
const failedStates: AutoSaveStatus[] = ['retrying', 'failed', 'conflict'];

watch(
    () => props.status,
    (next, previous) => {
        if (failedStates.includes(next)) {
            announcement.value =
                next === 'conflict'
                    ? 'Saving stopped because this session changed somewhere else.'
                    : 'Could not save. We will keep trying.';
        } else if (
            next === 'saved' &&
            previous &&
            failedStates.includes(previous)
        ) {
            announcement.value = 'All changes saved.';
        }
    },
);
</script>

<template>
    <span
        class="inline-flex min-w-8 items-center justify-end gap-1.5 text-xs font-medium whitespace-nowrap"
        :class="tone"
        :title="description"
    >
        <LoaderCircleIcon
            v-if="props.status === 'saving'"
            class="size-3.5 shrink-0 animate-spin"
            aria-hidden="true"
        />
        <CircleCheckIcon
            v-else-if="props.status === 'saved'"
            class="size-3.5 shrink-0"
            aria-hidden="true"
        />
        <RefreshCwIcon
            v-else-if="props.status === 'retrying'"
            class="size-3.5 shrink-0"
            aria-hidden="true"
        />
        <CircleHelpIcon
            v-else-if="props.status === 'needs_topic'"
            class="size-3.5 shrink-0"
            aria-hidden="true"
        />
        <TriangleAlertIcon
            v-else
            class="size-3.5 shrink-0"
            aria-hidden="true"
        />

        <span>{{ label }}</span>

        <span class="sr-only" role="status" aria-live="polite">{{
            announcement
        }}</span>
    </span>
</template>
