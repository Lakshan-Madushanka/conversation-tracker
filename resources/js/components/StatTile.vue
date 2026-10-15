<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: string | number;
    icon?: Component;
    hint?: string;
}>();
</script>

<template>
    <div class="rounded-xl border bg-card p-4">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">{{ label }}</p>
            <component
                :is="icon"
                v-if="icon"
                class="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
        </div>

        <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums">
            {{ value }}
        </p>
        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>
