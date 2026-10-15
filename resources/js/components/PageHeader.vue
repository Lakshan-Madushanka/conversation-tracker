<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <header class="mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight text-balance">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
