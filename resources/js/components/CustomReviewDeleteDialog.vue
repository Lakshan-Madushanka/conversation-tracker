<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Trash2Icon } from '@lucide/vue';
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
import {
    REVIEW_DELETE_WARNING,
    reviewDeleteAttemptsLabel,
    reviewDeleteTitle,
} from '@/lib/custom-review';

/**
 * The one confirmation every permanent review delete goes through.
 *
 * The card and the review's page both open this, so neither can word the
 * warning differently: it names the review, says how many attempts leave with
 * it, keeps Cancel as the safe default and promises nothing comes back. The
 * items are named where they stay, because that is what the learner worries
 * about most.
 */
const props = defineProps<{
    /** Whether the warning is on screen. */
    open: boolean;
    /** The review being deleted, as the warning names it. */
    name: string;
    /** How many attempts leave with the review. */
    attemptCount: number;
    /** Where the delete goes. */
    deleteUrl: string;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

/** True while the delete is on its way, so it cannot be pressed twice. */
const deleting = ref(false);

const attemptsLabel = reviewDeleteAttemptsLabel(props.attemptCount);

function confirmDelete(): void {
    if (deleting.value) {
        return;
    }

    deleting.value = true;
    router.delete(props.deleteUrl, {
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
    <AlertDialog
        :open="props.open"
        @update:open="emit('update:open', $event === true)"
    >
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ reviewDeleteTitle(props.name) }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    <p>{{ REVIEW_DELETE_WARNING }}</p>

                    <p v-if="attemptsLabel !== ''" class="mt-2">
                        {{ attemptsLabel }}
                    </p>
                </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogFooter>
                <AlertDialogCancel data-autofocus :disabled="deleting">
                    Cancel
                </AlertDialogCancel>
                <AlertDialogAction
                    variant="destructive"
                    :disabled="deleting"
                    @click="confirmDelete"
                >
                    <Trash2Icon aria-hidden="true" />
                    Delete review
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
