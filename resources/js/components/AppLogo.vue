<script setup lang="ts">
import { SparklesIcon } from '@lucide/vue';

defineProps<{
    /** On the narrow mobile header the brand drops its suffix. */
    compact?: boolean;
}>();
</script>

<template>
    <span class="flex items-center gap-2">
        <span
            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground"
            aria-hidden="true"
        >
            <SparklesIcon class="size-4" />
        </span>
        <span class="text-sm font-semibold tracking-tight">
            Spoken English<span v-if="!compact"> Companion</span>
        </span>
    </span>
</template>
