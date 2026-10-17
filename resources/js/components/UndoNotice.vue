<script setup lang="ts">
import { Button } from '@/components/ui/button';

/**
 * The "… Undo" line shown after a paste or a removal. The message itself is
 * announced by the live region the parent keeps mounted; this only shows the
 * words and the button that puts things back.
 */
const props = defineProps<{
    message: string;
    /** Accessible name for the button, so it says what it undoes. */
    undoLabel?: string;
}>();

const emit = defineEmits<{
    (event: 'undo'): void;
}>();
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-muted/40 px-2.5 py-1.5 text-xs text-muted-foreground"
    >
        <span>{{ props.message }}</span>
        <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-8"
            :aria-label="props.undoLabel ?? 'Undo'"
            @click="emit('undo')"
        >
            Undo
        </Button>
    </div>
</template>
