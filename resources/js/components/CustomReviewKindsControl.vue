<script setup lang="ts">
import { computed } from 'vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { CustomReviewKind } from '@/types';

/**
 * The kinds a review is allowed to hold, chosen whole.
 *
 * Every kind is chosen by default, which is what an unchanged review allows.
 * Turning a kind off is sent at once; the server refuses it while the review
 * still holds items of that kind and says which ones to remove, so the control
 * never silently drops anything. At least one kind is always left chosen,
 * because a review that allows nothing could hold nothing.
 */
const props = defineProps<{
    /** The kinds chosen, or null when the review allows every kind. */
    allowedKinds: CustomReviewKind[] | null;
    busy?: boolean;
}>();

const emit = defineEmits<{
    (event: 'change', kinds: CustomReviewKind[]): void;
}>();

const kinds: { value: CustomReviewKind; label: string }[] = [
    { value: 'vocabulary', label: 'Words' },
    { value: 'phrase', label: 'Phrases' },
    { value: 'grammar_note', label: 'Grammar notes' },
    { value: 'mistake', label: 'Mistakes' },
    { value: 'question', label: 'Questions' },
];

const chosen = computed<CustomReviewKind[]>(() =>
    props.allowedKinds === null
        ? kinds.map((kind) => kind.value)
        : props.allowedKinds,
);

function choose(value: unknown): void {
    if (!Array.isArray(value)) {
        return;
    }

    const next = kinds
        .map((kind) => kind.value)
        .filter((kind) => value.includes(kind));

    // Never let the last kind go: the server would refuse an empty choice.
    if (next.length === 0) {
        return;
    }

    const unchanged =
        next.length === chosen.value.length &&
        next.every((kind, index) => kind === chosen.value[index]);

    if (unchanged) {
        return;
    }

    emit('change', next);
}
</script>

<template>
    <ToggleGroup
        type="multiple"
        variant="outline"
        :model-value="chosen"
        :disabled="props.busy"
        aria-label="Kinds this review allows"
        class="h-auto w-full flex-wrap justify-start"
        @update:model-value="choose"
    >
        <ToggleGroupItem
            v-for="kind in kinds"
            :key="kind.value"
            :value="kind.value"
            class="min-h-11 px-4 md:min-h-9"
        >
            {{ kind.label }}
        </ToggleGroupItem>
    </ToggleGroup>
</template>
