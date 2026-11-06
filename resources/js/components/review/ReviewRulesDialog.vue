<script setup lang="ts">
import { ref } from 'vue';
import { InfoIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { ReviewRulesData } from '@/types';

/**
 * The rules behind Review, in plain words, with the numbers the app actually
 * runs on. The ratings say when an item comes back; the status says what the
 * library does with it. Nothing here decides either — it only explains them.
 */
const props = defineProps<{
    rules: ReviewRulesData;
}>();

const open = ref(false);

/** "1 day", "3 days" — the same wording for every gap on the screen. */
function daysLabel(value: number): string {
    return `${value} ${value === 1 ? 'day' : 'days'}`;
}

/** "in 7 days, then 14, then 30, then 60 days" from the saved Easy gaps. */
function easySentence(gaps: number[]): string {
    const [first, ...rest] = gaps;

    if (first === undefined) {
        return '';
    }

    if (rest.length === 0) {
        return `in ${daysLabel(first)}`;
    }

    return `in ${daysLabel(first)}, ${rest.map((gap) => `then ${gap}`).join(', ')} days`;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon">
                <InfoIcon aria-hidden="true" />
                <span class="sr-only">How reviews work</span>
            </Button>
        </DialogTrigger>

        <DialogContent class="max-h-[85svh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>How reviews work</DialogTitle>
                <DialogDescription>
                    Two things shape what you see next: the rating you give each
                    card, and the status you choose for an item.
                </DialogDescription>
            </DialogHeader>

            <section>
                <h3 class="font-medium text-foreground">Your rating</h3>
                <p class="mt-1">
                    A rating says how one card went in one sitting. It sets when
                    that item comes back:
                </p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    <li>
                        Hard — again in {{ daysLabel(props.rules.hardDays) }}.
                    </li>
                    <li>
                        Okay — again in {{ daysLabel(props.rules.okayDays) }}.
                    </li>
                    <li>
                        Easy — again
                        {{ easySentence(props.rules.easyGaps) }}. Every Easy
                        since your last Hard pushes it further out, and a Hard
                        brings it straight back.
                    </li>
                </ul>
            </section>

            <section>
                <h3 class="font-medium text-foreground">Your status</h3>
                <p class="mt-1">
                    A status is your own longer note on an item: Learned, Still
                    difficult, or nothing yet. You choose it in Collections, or
                    accept it when a review offers it. Nothing is ever marked
                    without you saying so.
                </p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    <li>
                        Learned — an item you have never reviewed stops coming
                        round as a new one. An item you have reviewed still
                        returns on its day for one last look.
                    </li>
                    <li>
                        Still difficult — comes ahead of other items that are
                        due the same day.
                    </li>
                    <li>
                        Status never changes the dates your ratings set, and a
                        rating never changes your status on its own.
                    </li>
                </ul>
            </section>

            <section>
                <h3 class="font-medium text-foreground">
                    Offers at the end of a review
                </h3>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    <li>
                        {{ props.rules.learnedRun }} Easy ratings in a row can
                        offer to mark an item Learned.
                    </li>
                    <li>
                        {{ props.rules.difficultRun }} Hard ratings in a row can
                        offer Still difficult.
                    </li>
                    <li>
                        One Okay in the middle starts the count again, and an
                        item already carrying that status is left alone.
                    </li>
                    <li>
                        At most {{ props.rules.maxSuggestions }} offers appear
                        at once. "Not now" changes nothing and is not asked
                        again for the same run.
                    </li>
                    <li>
                        Past questions and grammar notes are never offered a
                        status.
                    </li>
                </ul>
            </section>

            <section>
                <h3 class="font-medium text-foreground">A day's review</h3>
                <p class="mt-1">
                    {{ props.rules.dailySize }} items, and at most
                    {{ props.rules.newItems }} of them something you have never
                    reviewed before.
                </p>
            </section>
        </DialogContent>
    </Dialog>
</template>
