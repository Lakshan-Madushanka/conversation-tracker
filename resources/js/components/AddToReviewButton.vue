<script setup lang="ts">
import { CheckIcon, PlusIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/**
 * One item's place in a custom review, shown beside it in a collection list,
 * an opened item or a session's questions. The added state is written as
 * "Added", never colour alone, and says what it belongs to when a screen
 * reader reaches it.
 */
const props = withDefaults(
    defineProps<{
        /** The item's words, so the control names what it acts on. */
        name: string;
        /** Whether a review already holds this item. */
        added?: boolean;
        /** True while an add is on its way, so it cannot be pressed twice. */
        busy?: boolean;
        /** The icon-only look used among a row's marks. */
        compact?: boolean;
    }>(),
    {
        added: false,
        busy: false,
        compact: false,
    },
);

const emit = defineEmits<{
    (event: 'add'): void;
}>();
</script>

<template>
    <Button
        v-if="!props.added"
        type="button"
        :variant="props.compact ? 'ghost' : 'outline'"
        size="sm"
        :class="
            props.compact
                ? 'size-11 shrink-0 cursor-pointer p-0 md:size-8'
                : 'min-h-11 cursor-pointer gap-1.5 md:min-h-9'
        "
        :disabled="props.busy"
        :aria-label="`Add ${props.name} to review`"
        @click.stop="emit('add')"
    >
        <PlusIcon class="size-4" aria-hidden="true" />
        <template v-if="!props.compact">
            {{ props.busy ? 'Adding…' : 'Add' }}
        </template>
    </Button>

    <span
        v-else
        :title="props.compact ? 'In a review' : undefined"
        :class="
            props.compact
                ? 'inline-flex size-11 shrink-0 items-center justify-center text-muted-foreground md:size-8'
                : 'inline-flex min-h-11 items-center gap-1 text-xs font-medium text-muted-foreground md:min-h-9'
        "
    >
        <CheckIcon
            :class="props.compact ? 'size-4' : 'size-3.5'"
            aria-hidden="true"
        />
        <span class="sr-only">{{ props.name }} is in a review.</span>
        <span v-if="!props.compact" aria-hidden="true">Added</span>
    </span>
</template>
