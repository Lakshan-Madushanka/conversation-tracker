<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { focusElementById } from '@/lib/dom';

/**
 * Shown when a session has nothing to finish: a question card with no content,
 * or no questions at all. Finishing would save an empty session, so instead the
 * learner is offered the way out that makes sense — keep going, or throw it
 * away, asked for twice so nothing is discarded by a slip.
 */
const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'discard'): void;
}>();

/** False while the warning is showing; true once discard has been asked for. */
const confirming = ref(false);

watch(
    () => props.open,
    (open) => {
        if (open) {
            confirming.value = false;
        }
    },
);

/** Ask a second time, putting focus on the answer rather than on nothing. */
function askAgain(): void {
    confirming.value = true;
    focusElementById('empty-discard-confirm');
}

function discard(): void {
    emit('update:open', false);
    emit('discard');
}
</script>

<template>
    <AlertDialog :open="props.open" @update:open="emit('update:open', $event)">
        <AlertDialogContent>
            <AlertDialogHeader v-if="!confirming">
                <AlertDialogTitle>Nothing to finish yet</AlertDialogTitle>
                <AlertDialogDescription>
                    This session has no questions, or every question is still
                    empty. Add something first — or discard the session and
                    start again another time.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogHeader v-else>
                <AlertDialogTitle>Discard this session?</AlertDialogTitle>
                <AlertDialogDescription>
                    The session and anything in it will be removed. This cannot
                    be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogFooter v-if="!confirming">
                <AlertDialogCancel>Keep editing</AlertDialogCancel>
                <Button type="button" variant="outline" @click="askAgain">
                    Discard session
                </Button>
            </AlertDialogFooter>

            <AlertDialogFooter v-else>
                <AlertDialogCancel>Keep it</AlertDialogCancel>
                <AlertDialogAction
                    id="empty-discard-confirm"
                    variant="destructive"
                    @click="discard"
                >
                    Discard session
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
