<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CheckIcon } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { suggestionConfirm, suggestionDecline } from '@/routes/review';
import type { StatusSuggestionData } from '@/types';

/**
 * One status the finish screen offers, with the run of ratings behind it.
 *
 * Nothing changes until the learner presses a button. Marking confirms through
 * the same status rule Collections uses; "Not now" changes nothing and is only
 * asked again when a new run of ratings reaches the threshold.
 */
const props = defineProps<{
    reviewId: number;
    suggestion: StatusSuggestionData;
    /** Whether "Not now" takes focus, which only the first card should do. */
    defaultFocus?: boolean;
}>();

const answered = ref<'confirmed' | 'declined' | null>(null);

const ignoreButton = ref<{ $el?: unknown } | null>(null);

const form = useForm({
    kind: props.suggestion.kind,
    item_identity: props.suggestion.item_identity,
    status: props.suggestion.status,
    run_started_id: props.suggestion.run_started_id,
});

const statusWords = computed(() => props.suggestion.status_label.toLowerCase());

/** The plain reason, in words a screen reader reads calmly. */
const reason = computed(() => {
    const run = props.suggestion.run;
    const times = run === 1 ? 'once' : `${run} times`;

    return `You've rated this ${props.suggestion.rating_label.toLowerCase()} ${times} in a row. Would you like to mark it as ${statusWords.value}?`;
});

function confirmStatus(): void {
    form.post(suggestionConfirm.url({ review: props.reviewId }), {
        preserveScroll: true,
        onSuccess: () => {
            answered.value = 'confirmed';
            toast.success(`Marked as ${statusWords.value}.`);
        },
        onError: () => {
            toast.error('We could not save that status. Nothing was changed.');
        },
    });
}

function ignore(): void {
    form.post(suggestionDecline.url({ review: props.reviewId }), {
        preserveScroll: true,
        onSuccess: () => {
            answered.value = 'declined';
        },
        onError: () => {
            toast.error('We could not record that. Nothing was changed.');
        },
    });
}

onMounted(() => {
    if (props.defaultFocus !== true) {
        return;
    }

    void nextTick(() => {
        const target = ignoreButton.value?.$el;

        if (target instanceof HTMLElement) {
            target.focus();
        }
    });
});
</script>

<template>
    <Card class="border-primary/30 bg-primary/5 text-left">
        <CardHeader>
            <CardTitle class="text-sm font-medium">
                {{ props.suggestion.text }}
            </CardTitle>
            <CardDescription v-if="answered === null">{{
                reason
            }}</CardDescription>
        </CardHeader>

        <CardContent>
            <p
                v-if="answered === 'confirmed'"
                class="flex items-center gap-2 text-sm font-medium"
            >
                <CheckIcon class="size-4 text-primary" aria-hidden="true" />
                Marked as {{ statusWords }}.
            </p>

            <p
                v-else-if="answered === 'declined'"
                class="text-sm text-muted-foreground"
            >
                Not now — this stays as it is. A new run of ratings can ask
                again.
            </p>

            <div v-else class="flex flex-wrap items-center gap-2">
                <Button
                    class="cursor-pointer"
                    :disabled="form.processing"
                    @click="confirmStatus"
                >
                    Mark as {{ statusWords }}
                </Button>

                <Button
                    ref="ignoreButton"
                    variant="outline"
                    class="cursor-pointer"
                    :disabled="form.processing"
                    @click="ignore"
                >
                    Not now
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
