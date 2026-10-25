<script setup lang="ts">
import AppBottomNav from '@/components/AppBottomNav.vue';
import AppLogo from '@/components/AppLogo.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="min-h-dvh bg-muted/30">
        <a
            href="#main-content"
            class="sr-only rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground outline-none focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:ring-3 focus:ring-ring/50"
        >
            Skip to content
        </a>

        <div class="md:grid md:grid-cols-[16rem_minmax(0,1fr)]">
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
                    class="mx-auto w-full max-w-5xl flex-1 px-4 py-6 pb-24 md:px-8 md:pb-10"
                >
                    <slot />
                </main>

                <AppBottomNav />
            </div>
        </div>

        <Toaster position="top-center" />
    </div>
</template>
