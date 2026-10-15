<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PlusIcon, SearchIcon } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import SessionCard from '@/components/SessionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { sessions, topics } from '@/lib/sample-data';
import { create as sessionsCreate } from '@/routes/sessions';

defineOptions({ layout: AppLayout });
</script>

<template>
    <div>
        <PageHeader
            title="Library"
            description="Every practice session, newest first."
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
            <SessionCard
                v-for="session in sessions"
                :key="session.id"
                :session="session"
            />
        </div>

        <EmptyState
            v-else
            title="No sessions yet"
            description="Start your first practice session and it will show up here."
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

        <p class="mt-4 text-xs text-muted-foreground">
            Practice topics so far: {{ topics.join(', ') }}.
        </p>
    </div>
</template>
