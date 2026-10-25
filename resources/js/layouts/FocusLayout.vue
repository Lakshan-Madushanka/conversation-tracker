<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Toaster } from '@/components/ui/sonner';

defineProps<{
    /** Completion of the current exercise, as a percentage from 0 to 100. */
    progress: number;
    /** Where to go when the learner wants to stop early. */
    exitHref: string;
    /** Optional "2 of 8" style counter shown beside the progress bar. */
    position?: {
        current: number;
        total: number;
    };
    /** What the bar measures, in words: a review, or one guided question. */
    progressLabel?: string;
}>();
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-background">
        <header class="flex items-center gap-3 border-b px-4 py-3 sm:px-6">
            <!-- A page can take the exit over when leaving means going back
                into the page itself rather than to another address. -->
            <slot name="exit">
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="exitHref">
                        <ArrowLeftIcon aria-hidden="true" />
                        Exit
                    </Link>
                </Button>
            </slot>

            <div class="flex-1">
                <Progress
                    :model-value="progress"
                    :aria-label="progressLabel ?? 'Review progress'"
                />
            </div>

            <span
                v-if="position"
                class="shrink-0 text-sm text-muted-foreground tabular-nums"
            >
                {{ position.current }} of {{ position.total }}
            </span>
        </header>

        <main
            class="flex flex-1 items-center justify-center px-4 pb-10 sm:px-6"
        >
            <div class="w-full max-w-xl">
                <slot />
            </div>
        </main>

        <Toaster position="top-center" />
    </div>
</template>
