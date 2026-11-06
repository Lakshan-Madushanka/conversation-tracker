<script setup lang="ts">
import { CheckIcon, PlusIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/**
 * One item's place in the basket, shown beside it in a collection list or an
 * opened item. The added state is written as "Added", never colour alone, and
 * says what it belongs to when a screen reader reaches it.
 */
const props = withDefaults(
    defineProps<{
        /** The item's words, so the control names what it acts on. */
        name: string;
        /** Whether the basket already holds this item. */
        added?: boolean;
        /** True while an add is on its way, so it cannot be pressed twice. */
        busy?: boolean;
    }>(),
    {
        added: false,
        busy: false,
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
        variant="outline"
        size="sm"
        class="min-h-9 cursor-pointer gap-1.5"
        :disabled="props.busy"
        :aria-label="`Add ${props.name} to review`"
        @click.stop="emit('add')"
    >
        <PlusIcon class="size-4" aria-hidden="true" />
        {{ props.busy ? 'Adding…' : 'Add' }}
    </Button>

    <span
        v-else
        class="inline-flex min-h-9 items-center gap-1 text-xs font-medium text-muted-foreground"
    >
        <CheckIcon class="size-3.5" aria-hidden="true" />
        <span class="sr-only">{{ props.name }} is in the basket.</span>
        <span aria-hidden="true">Added</span>
    </span>
</template>
