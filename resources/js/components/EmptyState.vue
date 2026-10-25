<script setup lang="ts">
import { computed, type Component } from 'vue';
import { BookOpenIcon } from '@lucide/vue';

const props = defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();

/**
 * The icon is resolved here rather than as a prop default: Vue would call a
 * component passed as a default like a factory, and the icon would lose the
 * context it needs to render.
 */
const iconComponent = computed(() => props.icon ?? BookOpenIcon);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed bg-card/50 px-6 py-12 text-center"
    >
        <span
            class="mb-3 flex size-12 items-center justify-center rounded-full bg-muted"
            aria-hidden="true"
        >
            <component
                :is="iconComponent"
                class="size-6 text-muted-foreground"
            />
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
