<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { TopicSummary } from '@/types';

const props = defineProps<{
    modelValue: string;
    suggestions: TopicSummary[];
    error?: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

/**
 * Mirrors the server rule for spotting duplicate topics, so a chip looks
 * selected when it matches aside from case and extra spaces.
 */
function normalize(value: string): string {
    return value.trim().replace(/\s+/gu, ' ').toLowerCase();
}

const current = computed(() => normalize(props.modelValue));
</script>

<template>
    <div class="space-y-1.5">
        <label for="topic" class="text-sm font-medium">Topic</label>

        <Input
            id="topic"
            name="topic"
            :model-value="modelValue"
            placeholder="Ordering at a restaurant"
            autocomplete="off"
            :aria-invalid="Boolean(error)"
            :aria-describedby="error ? 'topic-error' : 'topic-hint'"
            @update:model-value="emit('update:modelValue', String($event))"
        />

        <p
            v-if="error"
            id="topic-error"
            role="alert"
            class="text-xs font-medium text-destructive"
        >
            {{ error }}
        </p>
        <p v-else id="topic-hint" class="text-xs text-muted-foreground">
            Pick a topic below, or type your own.
        </p>

        <div v-if="suggestions.length" class="space-y-1.5 pt-2">
            <p class="text-xs font-medium text-muted-foreground">Your topics</p>
            <div
                class="flex flex-wrap gap-2"
                role="group"
                aria-label="Your topics"
            >
                <button
                    v-for="topic in suggestions"
                    :key="topic.id"
                    type="button"
                    :aria-pressed="current === normalize(topic.name)"
                    :class="
                        cn(
                            'rounded-full border px-3 py-1 text-xs font-medium transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                            current === normalize(topic.name)
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )
                    "
                    @click="emit('update:modelValue', topic.name)"
                >
                    {{ topic.name }}
                </button>
            </div>
        </div>
    </div>
</template>
