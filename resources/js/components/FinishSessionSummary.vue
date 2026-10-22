<script setup lang="ts">
import { computed, ref } from 'vue';
import { TriangleAlertIcon } from '@lucide/vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import type { AttentionScan } from '@/lib/attention';
import type { FinishSummary } from '@/types';

/**
 * What finishing a session gives back: a short, plain sentence of what was
 * saved, a note when something still needs the learner's attention, and a way
 * straight to it. Nothing here is final — the session stays editable.
 */
const props = defineProps<{
    open: boolean;
    /** The counts the server sent back when the session was finished. */
    result: FinishSummary;
    /** Rows still missing a correction or a meaning, and where the first is. */
    attention: AttentionScan;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'review'): void;
}>();

function counted(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

/** The sentence naming everything that was saved, in the order it was entered. */
const savedLine = computed(() => {
    const counts = props.result.counts;

    const parts = [
        counted(counts.questions, 'question', 'questions'),
        counted(counts.words, 'new word', 'new words'),
        counted(counts.phrases, 'phrase', 'phrases'),
        counted(counts.mistakes, 'mistake', 'mistakes'),
        counted(counts.grammar_notes, 'grammar note', 'grammar notes'),
    ];

    return `${parts.slice(0, -1).join(', ')}, and ${parts.at(-1)}`;
});

/**
 * Set when the learner asks to be taken to a row still needing work. The
 * dialog would otherwise put focus back where it came from, which would undo
 * the move, so that return is stopped for this close only.
 */
const reviewRequested = ref(false);

function review(): void {
    reviewRequested.value = true;
    emit('update:open', false);
    emit('review');
}

function handleCloseAutoFocus(event: Event): void {
    if (!reviewRequested.value) {
        return;
    }

    reviewRequested.value = false;
    event.preventDefault();
}
</script>

<template>
    <AlertDialog :open="props.open" @update:open="emit('update:open', $event)">
        <AlertDialogContent @close-auto-focus="handleCloseAutoFocus">
            <AlertDialogHeader>
                <AlertDialogTitle>Session finished</AlertDialogTitle>
                <AlertDialogDescription>
                    Your practice is saved: {{ savedLine }}. You can reopen it
                    any time and carry on adding to it.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <p
                v-if="props.result.empty_questions_removed > 0"
                class="text-sm text-muted-foreground"
            >
                {{
                    counted(
                        props.result.empty_questions_removed,
                        'question card held nothing',
                        'question cards held nothing',
                    )
                }}
                and
                {{
                    props.result.empty_questions_removed === 1 ? 'was' : 'were'
                }}
                removed.
            </p>

            <p
                v-if="props.attention.count > 0"
                class="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm"
            >
                <TriangleAlertIcon
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{
                    counted(
                        props.attention.count,
                        'row still needs',
                        'rows still need',
                    )
                }}
                a correction or a meaning.
            </p>

            <AlertDialogFooter>
                <Button
                    v-if="props.attention.first !== null"
                    type="button"
                    variant="outline"
                    @click="review"
                >
                    Review them
                </Button>
                <AlertDialogAction>Done</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
