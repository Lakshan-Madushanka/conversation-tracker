<script setup lang="ts">
import { CircleXIcon, EqualIcon, ThumbsUpIcon } from '@lucide/vue';
import { cn } from 'cn';

export type ReviewRating = 'hard' | 'okay' | 'easy';

const emit = defineEmits<{
    rate: [rating: ReviewRating];
}>();

const options: {
    rating: ReviewRating;
    label: string;
    icon: typeof CircleXIcon;
    classes: string;
}[] = [
    {
        rating: 'hard',
        label: 'Hard',
        icon: CircleXIcon,
        classes:
            'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300 dark:hover:bg-rose-950',
    },
    {
        rating: 'okay',
        label: 'Okay',
        icon: EqualIcon,
        classes:
            'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300 dark:hover:bg-amber-950',
    },
    {
        rating: 'easy',
        label: 'Easy',
        icon: ThumbsUpIcon,
        classes:
            'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300 dark:hover:bg-emerald-950',
    },
];
</script>

<template>
    <div
        class="grid grid-cols-3 gap-2 sm:gap-3"
        role="group"
        aria-label="How did that go?"
    >
        <button
            v-for="option in options"
            :key="option.rating"
            type="button"
            :class="
                cn(
                    'flex items-center justify-center gap-2 rounded-xl border px-3 py-3 text-sm font-medium transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                    option.classes,
                )
            "
            @click="emit('rate', option.rating)"
        >
            <component
                :is="option.icon"
                class="size-4 shrink-0"
                aria-hidden="true"
            />
            {{ option.label }}
        </button>
    </div>
</template>
