<script setup lang="ts">
import type { Component } from 'vue';
import { BookOpenIcon } from '@lucide/vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
    }>(),
    {
        icon: BookOpenIcon,
    },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed bg-card/50 px-6 py-12 text-center"
    >
        <span
            class="mb-3 flex size-12 items-center justify-center rounded-full bg-muted"
            aria-hidden="true"
        >
            <component :is="icon" class="size-6 text-muted-foreground" />
        </span>
        <h2 class="text-base font-semibold">{{ title }}</h2>
        <p
            v-if="description"
            class="mt-1 max-w-sm text-sm text-muted-foreground"
        >
            {{ description }}
        </p>

        <div v-if="$slots.actions" class="mt-5">
            <slot name="actions" />
        </div>
    </div>
</template>
