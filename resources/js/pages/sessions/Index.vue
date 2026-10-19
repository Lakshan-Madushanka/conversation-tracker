<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PlusIcon, SearchIcon } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import FinishedSessionCard from '@/components/FinishedSessionCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { create as sessionsCreate } from '@/routes/sessions';
import type { FinishedSessionData } from '@/types';

defineOptions({ layout: AppLayout });

defineProps<{
    /** Every finished session, newest first. */
    sessions: FinishedSessionData[];
}>();
</script>

<template>
    <div>
        <PageHeader
            title="Library"
            description="Every session you have finished, newest first."
        >
            <template #actions>
                <Button size="lg" as-child>
                    <Link :href="sessionsCreate.url()">
                        <PlusIcon aria-hidden="true" />
                        New session
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Search + filters. Placeholders only for now: saving, searching and
             filtering are built in a later step. -->
        <div class="mb-5 space-y-3">
            <div class="relative">
                <SearchIcon
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    type="search"
                    placeholder="Search words, phrases, mistakes"
                    class="pl-9"
                    aria-label="Search your sessions"
                />
            </div>

            <div
                class="flex flex-wrap items-center gap-2"
                role="group"
                aria-label="Filters"
            >
                <SectionLabel tone="neutral">Topic</SectionLabel>
                <SectionLabel tone="neutral">Date</SectionLabel>
                <SectionLabel tone="neutral">Content type</SectionLabel>
            </div>
        </div>

        <div v-if="sessions.length > 0" class="grid gap-3">
            <FinishedSessionCard
                v-for="session in sessions"
                :key="session.id"
                :session="session"
            />
        </div>

        <EmptyState
            v-else
            title="No sessions yet"
            description="Finish your first practice session and it will show up here."
        >
            <template #actions>
                <Button size="lg" as-child>
                    <Link :href="sessionsCreate.url()">
                        <PlusIcon aria-hidden="true" />
                        Start your first session
                    </Link>
                </Button>
            </template>
        </EmptyState>
    </div>
</template>
