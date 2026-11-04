<script setup lang="ts">
import { provide, ref } from 'vue';
import AppBottomNav from '@/components/AppBottomNav.vue';
import AppLogo from '@/components/AppLogo.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Toaster } from '@/components/ui/sonner';
import { useSidebar } from '@/composables/useSidebar';
import { bottomNavHiddenKey } from '@/lib/layout';

/** Pages can ask the bottom navigation to step aside for a moment. */
const bottomNavHidden = ref(false);

provide(bottomNavHiddenKey, bottomNavHidden);

const { collapsed: sidebarCollapsed } = useSidebar();
</script>

<template>
    <div class="min-h-dvh bg-muted/30">
        <a
            href="#main-content"
            class="sr-only rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground outline-none focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:ring-3 focus:ring-ring/50"
        >
            Skip to content
        </a>

        <div
            class="md:grid md:transition-[grid-template-columns] md:duration-200 md:ease-in-out"
            :class="
                sidebarCollapsed
                    ? 'md:grid-cols-[4rem_minmax(0,1fr)]'
                    : 'md:grid-cols-[16rem_minmax(0,1fr)]'
            "
        >
            <AppSidebar />

            <div class="flex min-h-dvh min-w-0 flex-col">
                <header
                    class="sticky top-0 z-30 flex h-14 items-center justify-between border-b bg-background/80 px-4 backdrop-blur md:hidden"
                >
                    <AppLogo compact />
                    <ThemeToggle />
                </header>

                <main
                    id="main-content"
                    tabindex="-1"
                    class="mx-auto w-full flex-1 py-6"
                    :class="{
                        'pb-48 md:pb-44': bottomNavHidden,
                        'pb-24 md:pb-10': !bottomNavHidden,
                        'max-w-6xl px-4': sidebarCollapsed,
                        'max-w-5xl px-4 md:px-8': !sidebarCollapsed,
                    }"
                >
                    <slot />
                </main>

                <AppBottomNav v-if="!bottomNavHidden" />
            </div>
        </div>

        <Toaster position="top-center" />
    </div>
</template>
