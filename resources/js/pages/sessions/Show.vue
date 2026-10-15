<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, EllipsisIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionLabel from '@/components/SectionLabel.vue';
import type { FeedbackTone } from '@/components/SectionLabel.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { sessionDetail } from '@/lib/sample-data';
import { index as sessionsIndex } from '@/routes/sessions';

defineOptions({ layout: AppLayout });

interface FeedbackRow {
    label: string;
    tone: FeedbackTone;
    value: string;
}

function feedbackRows(entry: {
    mistakes: string;
    vocabulary: string;
    phrases: string;
    grammar: string;
    natural: string;
}): FeedbackRow[] {
    const rows: FeedbackRow[] = [
        { label: 'Mistakes', tone: 'mistakes', value: entry.mistakes },
        { label: 'Vocabulary', tone: 'vocabulary', value: entry.vocabulary },
        { label: 'Phrases', tone: 'phrases', value: entry.phrases },
        { label: 'Grammar', tone: 'grammar', value: entry.grammar },
        { label: 'Natural version', tone: 'natural', value: entry.natural },
    ];

    return rows.filter((row) => row.value.trim().length > 0);
}

const entries = computed(() => sessionDetail.entries);
</script>

<template>
    <div>
        <Link
            :href="sessionsIndex.url()"
            class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground"
        >
            <ArrowLeftIcon class="size-4" aria-hidden="true" />
            Library
        </Link>

        <PageHeader
            :title="sessionDetail.topic"
            :description="`Recorded ${sessionDetail.date} · ${sessionDetail.questions} questions`"
        >
            <template #actions>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Session actions"
                        >
                            <EllipsisIcon aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem>Edit session</DropdownMenuItem>
                        <DropdownMenuItem>Archive</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                        >
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </PageHeader>

        <div class="grid gap-4">
            <Card v-for="entry in entries" :key="entry.id">
                <CardHeader>
                    <CardTitle class="text-base">
                        Question {{ entry.id }}
                    </CardTitle>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="space-y-3">
                        <div>
                            <p
                                class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Tutor’s question
                            </p>
                            <p>{{ entry.question }}</p>
                        </div>

                        <div>
                            <p
                                class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                My answer
                            </p>
                            <p class="text-muted-foreground">
                                {{ entry.answer }}
                            </p>
                        </div>
                    </div>

                    <Separator />

                    <dl class="space-y-3">
                        <div
                            v-for="row in feedbackRows(entry)"
                            :key="row.label"
                        >
                            <dt class="mb-1">
                                <SectionLabel :tone="row.tone">{{
                                    row.label
                                }}</SectionLabel>
                            </dt>
                            <dd class="text-sm">{{ row.value }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
