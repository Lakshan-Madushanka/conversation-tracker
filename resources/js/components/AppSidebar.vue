<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from 'cn';
import AppLogo from '@/components/AppLogo.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { sidebarNav, useActiveHref } from '@/lib/navigation';

const { activeWithin } = useActiveHref();
const activeHref = computed(() =>
    activeWithin(sidebarNav.map((item) => item.href)),
);
</script>

<template>
    <aside
        class="sticky top-0 hidden h-dvh flex-col border-r bg-sidebar md:flex"
        aria-label="Primary"
    >
        <div class="flex h-16 shrink-0 items-center px-5">
            <AppLogo />
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
            <Link
                v-for="item in sidebarNav"
                :key="item.href"
                :href="item.href"
                :aria-current="activeHref === item.href ? 'page' : undefined"
                :class="
                    cn(
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                        activeHref === item.href
                            ? 'bg-sidebar-accent text-sidebar-primary'
                            : 'text-sidebar-foreground hover:bg-sidebar-accent/60',
                    )
                "
            >
                <component
                    :is="item.icon"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ item.label }}</span>
            </Link>
        </nav>

        <div class="shrink-0 border-t p-3">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-muted-foreground">
                    Personal practice log
                </p>
                <ThemeToggle />
            </div>
        </div>
    </aside>
</template>
