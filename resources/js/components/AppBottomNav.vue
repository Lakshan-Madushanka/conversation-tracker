<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon, EllipsisIcon } from '@lucide/vue';
import { cn } from 'cn';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    mobileMoreNav,
    mobilePrimaryNav,
    useActiveHref,
} from '@/lib/navigation';

const { activeWithin } = useActiveHref();
const moreIsActive = computed(
    () => activeWithin(mobileMoreNav.map((item) => item.href)) !== '',
);
const moreOpen = ref(false);

/** Whether this specific link is the current page, within a nav's own hrefs. */
const isCurrent = (href: string) => activeWithin([href]) === href;

const linkClasses = (active: boolean) =>
    cn(
        'flex h-16 flex-col items-center justify-center gap-1 px-1 text-center text-[0.7rem] font-medium transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
        active ? 'text-primary' : 'text-muted-foreground',
    );
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        aria-label="Primary"
    >
        <ul class="grid grid-cols-5">
            <li v-for="item in mobilePrimaryNav" :key="item.href">
                <Link
                    :href="item.href"
                    :aria-current="isCurrent(item.href) ? 'page' : undefined"
                    :class="linkClasses(isCurrent(item.href))"
                >
                    <component
                        :is="item.icon"
                        class="size-5"
                        aria-hidden="true"
                    />
                    <span>{{ item.shortLabel ?? item.label }}</span>
                </Link>
            </li>

            <li>
                <Sheet v-model:open="moreOpen">
                    <SheetTrigger as-child>
                        <button
                            type="button"
                            :class="linkClasses(moreIsActive)"
                            aria-haspopup="dialog"
                        >
                            <EllipsisIcon class="size-5" aria-hidden="true" />
                            <span>More</span>
                        </button>
                    </SheetTrigger>

                    <SheetContent side="bottom" class="rounded-t-2xl">
                        <SheetHeader>
                            <SheetTitle>More</SheetTitle>
                            <SheetDescription>
                                Other places in the app.
                            </SheetDescription>
                        </SheetHeader>

                        <ul class="grid gap-1 pb-4">
                            <li v-for="item in mobileMoreNav" :key="item.href">
                                <Link
                                    :href="item.href"
                                    :aria-current="
                                        isCurrent(item.href)
                                            ? 'page'
                                            : undefined
                                    "
                                    :class="
                                        cn(
                                            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            isCurrent(item.href)
                                                ? 'bg-accent text-primary'
                                                : 'hover:bg-accent',
                                        )
                                    "
                                    @click="moreOpen = false"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="flex-1">{{ item.label }}</span>
                                    <ChevronRightIcon
                                        class="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </li>
                        </ul>
                    </SheetContent>
                </Sheet>
            </li>
        </ul>
    </nav>
</template>
