<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    /** An id for the heading, so after an action focus can land here. */
    headingId?: string;
}>();
</script>

<template>
    <header class="mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1
                    :id="headingId"
                    :tabindex="headingId === undefined ? undefined : -1"
                    class="text-2xl font-semibold tracking-tight text-balance outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>

            <div
                v-if="$slots.actions"
                class="flex max-w-full flex-wrap items-center gap-2"
            >
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
