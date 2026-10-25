<script setup lang="ts">
import { ChevronRightIcon } from '@lucide/vue';
import SaveIndicator from '@/components/SaveIndicator.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AutoSaveStatus } from '@/composables/useAutoSave';
import { GUIDED_STEPS } from '@/lib/guided';

const props = withDefaults(
    defineProps<{
        /** The chip beside the step counter, for example "Mistakes". */
        label: string;
        /** Which of the seven steps this is, or null when they are not counted. */
        stepNumber: number | null;
        /** The heading over the field. */
        heading: string;
        /** One calm line saying what this step is for. */
        hint: string;
        /** A small line about what is already in the box, or null. */
        detail?: string | null;
        status: AutoSaveStatus;
        /** When the last save went through, in epoch milliseconds. */
        lastSavedAt: number | null;
        /** Whether there is a step behind this one to go back to. */
        canGoBack: boolean;
        /** Whether the learner has put something in, so the button says Next. */
        filled?: boolean;
        /** The review screen shows its own actions instead of a Next button. */
        nextVisible?: boolean;
        /** An id for the heading, so focus can be sent to it on arrival. */
        headingId?: string;
    }>(),
    { detail: null, filled: false, nextVisible: true, headingId: undefined },
);

const emit = defineEmits<{
    (event: 'back'): void;
    (event: 'next'): void;
}>();

/** Ctrl/Cmd+Enter moves on, so a long paste never needs a reach for the mouse. */
function handleKeyDown(event: KeyboardEvent): void {
    if (event.key !== 'Enter' || !(event.ctrlKey || event.metaKey)) {
        return;
    }

    if (props.nextVisible === false) {
        return;
    }

    event.preventDefault();
    emit('next');
}
</script>

<template>
    <div class="grid gap-4" @keydown="handleKeyDown">
        <div class="flex flex-wrap items-center gap-2">
            <Badge variant="outline">{{ props.label }}</Badge>

            <span
                v-if="props.stepNumber !== null"
                class="text-xs text-muted-foreground tabular-nums"
            >
                Step {{ props.stepNumber }} of {{ GUIDED_STEPS.length }}
            </span>

            <SaveIndicator
                class="ml-auto"
                :status="props.status"
                :last-saved-at="props.lastSavedAt"
            />
        </div>

        <div class="grid gap-1">
            <h1 :id="props.headingId" class="text-lg font-semibold">
                {{ props.heading }}
            </h1>
            <p class="text-sm text-muted-foreground">{{ props.hint }}</p>
            <p v-if="props.detail" class="text-xs text-muted-foreground">
                {{ props.detail }}
            </p>
        </div>

        <slot />

        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-if="props.canGoBack"
                type="button"
                variant="outline"
                @click="emit('back')"
            >
                Back
            </Button>

            <Button
                v-if="props.nextVisible !== false"
                type="button"
                class="ml-auto"
                @click="emit('next')"
            >
                {{ props.filled ? 'Next' : 'Skip' }}
                <ChevronRightIcon aria-hidden="true" />
            </Button>
        </div>
    </div>
</template>
