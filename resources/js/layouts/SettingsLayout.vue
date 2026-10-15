<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from 'cn';
import PageHeader from '@/components/PageHeader.vue';
import { settingsNav, useActiveHref } from '@/lib/navigation';

const { activeWithin } = useActiveHref();
const activeHref = computed(() =>
    activeWithin(settingsNav.map((item) => item.href)),
);
</script>

<template>
    <div>
        <PageHeader
            title="Settings"
            description="Your preferences, plus import and export."
        />

        <div class="md:grid md:grid-cols-[12rem_minmax(0,1fr)] md:gap-8">
            <!-- Desktop / tablet: a small secondary nav. On phones the same
                 links become a horizontally scrollable tab strip. -->
            <nav
                class="-mx-4 mb-6 flex gap-1 overflow-x-auto px-4 md:mx-0 md:mb-0 md:flex-col md:px-0"
                aria-label="Settings sections"
            >
                <Link
                    v-for="item in settingsNav"
                    :key="item.href"
                    :href="item.href"
                    :aria-current="
                        activeHref === item.href ? 'page' : undefined
                    "
                    :class="
                        cn(
                            'inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                            activeHref === item.href
                                ? 'bg-accent text-primary'
                                : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                        )
                    "
                >
                    <component
                        :is="item.icon"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="min-w-0">
                <slot />
            </div>
        </div>
    </div>
</template>
