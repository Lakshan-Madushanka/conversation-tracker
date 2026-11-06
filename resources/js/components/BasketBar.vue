<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ShoppingBasketIcon } from '@lucide/vue';
import { basketBarLabel, basketCount, currentBasketReturn } from '@/lib/basket';
import { index as basketIndex } from '@/routes/basket';

/**
 * The reminder that a custom review is being gathered. It sits above the
 * bottom navigation on a phone and at the top of the content on a larger
 * screen, so it never hides the end of a list, a message or the navigation.
 *
 * It reads the shared count, so adding, removing or clearing an item changes
 * it straight away without asking the server for the page again.
 */
const count = basketCount;

const href = computed(() =>
    basketIndex.url({ query: { from: currentBasketReturn() } }),
);

const label = computed(() => basketBarLabel(count.value));
</script>

<template>
    <div
        v-if="count > 0"
        class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 px-4 md:static md:inset-auto md:z-auto md:mb-4 md:px-0"
    >
        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ label }}
        </p>

        <Link
            :href="href"
            class="flex min-h-12 items-center justify-between gap-3 rounded-xl border bg-background/95 px-4 py-2 shadow-lg backdrop-blur transition-colors hover:bg-muted/60 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none md:shadow-none"
        >
            <span class="flex min-w-0 items-center gap-2 text-sm font-medium">
                <ShoppingBasketIcon
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span class="truncate">{{ label }}</span>
            </span>

            <span class="shrink-0 text-sm font-medium text-primary">
                Open basket
            </span>
        </Link>
    </div>
</template>
