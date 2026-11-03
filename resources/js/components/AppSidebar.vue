<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from 'cn';
import { PanelLeftCloseIcon, PanelLeftOpenIcon } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { useSidebar } from '@/composables/useSidebar';
import { sidebarNav, useActiveHref } from '@/lib/navigation';

const { collapsed, toggleSidebar } = useSidebar();

const { activeWithin } = useActiveHref();

const activeHref = computed(() =>
    activeWithin(sidebarNav.map((item) => item.href)),
);

const sidebarClass = computed(() => {
    const baseClasses =
        'sticky top-0 hidden h-dvh shrink-0 flex-col border-r bg-sidebar transition-[width] duration-200 ease-in-out md:flex';

    return collapsed.value ? `${baseClasses} w-16` : `${baseClasses} w-64`;
});
</script>

<template>
    <aside :class="sidebarClass" aria-label="Primary">
        <div
            class="flex h-16 shrink-0 items-center"
            :class="collapsed ? 'justify-center' : 'justify-between gap-2 px-5'"
        >
            <AppLogo v-if="!collapsed" />

            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-md text-sidebar-foreground transition-colors outline-none hover:bg-sidebar-accent/60 hover:text-sidebar-primary focus-visible:ring-3 focus-visible:ring-ring/50"
                :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :aria-expanded="!collapsed"
                @click="toggleSidebar"
            >
                <PanelLeftOpenIcon
                    v-if="collapsed"
                    class="size-5 shrink-0"
                    aria-hidden="true"
                />
                <PanelLeftCloseIcon
                    v-else
                    class="size-5 shrink-0"
                    aria-hidden="true"
                />
            </button>
        </div>

        <nav
            class="flex-1 space-y-1 overflow-y-auto py-2"
            :class="collapsed ? 'px-2' : 'px-3'"
        >
            <Link
                v-for="item in sidebarNav"
                :key="item.href"
                :href="item.href"
                :aria-current="activeHref === item.href ? 'page' : undefined"
                :aria-label="collapsed ? item.label : undefined"
                :title="collapsed ? item.label : undefined"
                :class="
                    cn(
                        'flex items-center rounded-lg text-sm font-medium transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                        collapsed ? 'justify-center p-2.5' : 'gap-3 px-3 py-2',
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
                <span v-if="!collapsed">{{ item.label }}</span>
            </Link>
        </nav>

        <div
            class="shrink-0 border-t p-3"
            :class="collapsed ? 'flex justify-center' : ''"
        >
            <div
                v-if="!collapsed"
                class="flex items-center justify-between gap-2"
            >
                <p class="text-xs text-muted-foreground">
                    Personal practice log
                </p>
                <ThemeToggle />
            </div>
            <ThemeToggle v-else />
        </div>
    </aside>
</template>
