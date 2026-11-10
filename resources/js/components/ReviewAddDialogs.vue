<script setup lang="ts">
import { TriangleAlertIcon } from '@lucide/vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    asking,
    cancelAdd,
    chooseReview,
    confirmAddSize,
    refusalMessage,
    reviewChoices,
    reviewItemCountLabel,
    reviewSizeMessage,
    resultingCount,
} from '@/lib/custom-review';

/**
 * The two questions an add can ask, and the one refusal it can hear.
 *
 * The server decides whether either question is needed, so this reads its
 * answer and shows it: which review the items belong to when several are
 * being built, and whether a review past the recommended size is what the
 * learner meant. Both keep Cancel working as an ordinary cancel — nothing is
 * added, nothing is said about it — and every control is a real button, so the
 * keyboard and a screen reader reach the same choices as a mouse.
 *
 * A refusal asks nothing: the Alert says what the server said, and stands
 * until the next add replaces it.
 */
/**
 * A dialog closing of its own accord — Escape, the overlay, the outside — is
 * the learner changing their mind. An answer already given closes the same
 * dialog, so only a question still open counts as a cancel.
 */
function closeQuestion(open: boolean): void {
    if (!open && asking.value !== '') {
        cancelAdd();
    }
}
</script>

<template>
    <div>
        <Alert v-if="refusalMessage !== ''" class="mb-6">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Nothing was added</AlertTitle>
            <AlertDescription>{{ refusalMessage }}</AlertDescription>
        </Alert>

        <Dialog :open="asking === 'review'" @update:open="closeQuestion">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Which review are these going into?
                    </DialogTitle>
                    <DialogDescription>
                        You have more than one review still being built. Choose
                        one — the first is the most recently created.
                    </DialogDescription>
                </DialogHeader>

                <ul class="flex flex-col gap-2">
                    <li
                        v-for="(review, index) in reviewChoices"
                        :key="review.id"
                    >
                        <Button
                            type="button"
                            :variant="index === 0 ? 'default' : 'outline'"
                            class="flex min-h-11 w-full flex-col items-start gap-0.5 text-left"
                            @click="chooseReview(review.id)"
                        >
                            <span class="text-sm font-medium">{{
                                review.name
                            }}</span>
                            <span
                                class="text-xs font-normal opacity-80"
                                :class="
                                    index === 0 ? '' : 'text-muted-foreground'
                                "
                            >
                                {{ review.status_label }} ·
                                {{ reviewItemCountLabel(review.item_count) }}
                            </span>
                        </Button>
                    </li>
                </ul>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            class="min-h-11 md:min-h-9"
                            @click="cancelAdd"
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog :open="asking === 'size'" @update:open="closeQuestion">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Add all of them anyway?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ reviewSizeMessage(resultingCount) }} Nothing is left
                        out, and you can take items out later.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel
                        data-autofocus
                        class="min-h-11 md:min-h-9"
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction as-child>
                        <Button
                            type="button"
                            class="min-h-11 md:min-h-9"
                            @click="confirmAddSize"
                        >
                            Continue
                        </Button>
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
