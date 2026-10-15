<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { StarIcon } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CollectionItem, CollectionKey } from '@/lib/sample-data';
import { collections } from '@/lib/sample-data';
import { index as sessionsIndex } from '@/routes/sessions';

defineOptions({ layout: AppLayout });

const tabs: { key: CollectionKey; label: string }[] = [
    { key: 'vocabulary', label: 'Vocabulary' },
    { key: 'phrases', label: 'Phrases' },
    { key: 'grammar', label: 'Grammar' },
    { key: 'mistakes', label: 'My mistakes' },
];

const statusLabel: Record<CollectionItem['status'], string> = {
    new: 'New',
    learned: 'Learned',
    difficult: 'Still difficult',
};

const statusVariant: Record<
    CollectionItem['status'],
    'secondary' | 'outline' | 'destructive'
> = {
    new: 'secondary',
    learned: 'outline',
    difficult: 'destructive',
};
</script>

<template>
    <div>
        <PageHeader
            title="Collections"
            description="Vocabulary, phrases, grammar notes and your common mistakes, gathered from every session."
        />

        <Tabs default-value="vocabulary">
            <TabsList class="grid w-full grid-cols-4">
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.key"
                    :value="tab.key"
                >
                    {{ tab.label }}
                </TabsTrigger>
            </TabsList>

            <TabsContent
                v-for="tab in tabs"
                :key="tab.key"
                :value="tab.key"
                class="mt-4"
            >
                <div class="grid gap-3">
                    <div
                        v-for="item in collections[tab.key]"
                        :key="item.id"
                        class="rounded-xl border bg-card p-4"
                    >
                        <div class="flex items-start gap-3">
                            <button
                                type="button"
                                class="mt-0.5 shrink-0 rounded focus-visible:ring-3 focus-visible:ring-ring/50"
                                :aria-label="`Star ${item.term}`"
                                :aria-pressed="item.starred"
                            >
                                <StarIcon
                                    class="size-4"
                                    :class="
                                        item.starred
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-muted-foreground'
                                    "
                                />
                            </button>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium">
                                        {{ item.term }}
                                    </p>
                                    <Badge
                                        :variant="statusVariant[item.status]"
                                    >
                                        {{ statusLabel[item.status] }}
                                    </Badge>
                                    <Badge v-if="item.seen" variant="secondary">
                                        Seen {{ item.seen }} times
                                    </Badge>
                                </div>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ item.meaning }}
                                </p>
                                <p
                                    v-if="item.example"
                                    class="mt-1 text-sm text-muted-foreground italic"
                                >
                                    “{{ item.example }}”
                                </p>

                                <div
                                    class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                >
                                    <span>{{ item.sessionTopic }}</span>
                                    <Link
                                        :href="sessionsIndex.url()"
                                        class="font-medium text-primary hover:underline"
                                    >
                                        See original session
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
