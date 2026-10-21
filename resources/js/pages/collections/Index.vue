<script setup lang="ts">
import {reactive, ref, watch} from "vue";
import { router } from "@inertiajs/vue3";
import {InfiniteScroll, Link} from '@inertiajs/vue3';
import type { InfiniteScrollRef } from '@inertiajs/core';
import { StarIcon, AlertCircleIcon  } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input'
import { Spinner } from '@/components/ui/spinner'
import {Alert, AlertDescription, AlertTitle} from "@/components/ui/alert";
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { show as sessionsShow } from '@/routes/sessions';
import { index as collectionIndex } from '@/routes/collections';
import { create as sessionsCreate } from '@/routes/sessions';
import type { CollectionItem, CollectionKey } from '@/lib/sample-data';
import {debounce} from "@/lib/debounce";
import {LaravelPaginator, PracticeFeedbackEntry} from "@/types/types";

defineOptions({ layout: AppLayout });

const props = defineProps<{
    vocabulary: LaravelPaginator<PracticeFeedbackEntry>;
    phrases: LaravelPaginator<PracticeFeedbackEntry>;
    grammar: LaravelPaginator<PracticeFeedbackEntry>;
    mistakes: LaravelPaginator<PracticeFeedbackEntry>;
}>()

const currentTabKey = ref<CollectionKey>('vocabulary')

const filters = reactive<Record<CollectionKey, { search: string|number|undefined }>>({
    vocabulary: {search: '',},
    phrases: {search: '', },
    grammar: {search: '', },
    mistakes: {search: '', },
})

const infiniteScrollRefs = reactive<Record<CollectionKey, InfiniteScrollRef | null>>({
    vocabulary: null,
    phrases: null,
    grammar: null,
    mistakes: null,
})

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

watch(filters, () => {
    search()
})

function onTabChange(tab: CollectionKey) {
    currentTabKey.value = tab;
    infiniteScrollRefs[tab]?.fetchNext();
}

function setInfiniteScrollRef(key: CollectionKey, el: unknown): void {
    infiniteScrollRefs[key] = el as InfiniteScrollRef | null;
}

function getCurrentTabLabel() {
    return tabs.find((tab) => currentTabKey.value === tab.key)?.label;
}

const search = debounce(function search() {
    let term = filters[currentTabKey.value].search

    router.visit(collectionIndex.url(), {
        data: { search: term, forPage: currentTabKey.value },
        only: [currentTabKey.value],
        reset: [currentTabKey.value],
        preserveState: true,
        preserveUrl: true,
    });
}, 300)
</script>

<template>
    <div>
        <PageHeader
            title="Collections"
            description="Vocabulary, phrases, grammar notes and your common mistakes, gathered from every session."
        />

        <Tabs  default-value="vocabulary">
            <TabsList class="grid w-full grid-cols-4">
                <TabsTrigger
                    class="cursor-pointer"
                    v-for="tab in tabs"
                    :key="tab.key"
                    :value="tab.key"
                    @click="onTabChange(tab.key)"
                >
                    {{ tab.label }}
                </TabsTrigger>
            </TabsList>

            <Input
                v-model="filters[currentTabKey].search"
                class="mt-4"
                type="search"
                :placeholder="`Search ${getCurrentTabLabel()}`"
            />

            <TabsContent
                v-for="tab in tabs"
                :key="tab.key"
                :value="tab.key"
                class="mt-4"
            >
                <InfiniteScroll  :ref="(el) => setInfiniteScrollRef(tab.key, el)" :data="tab.key">
                    <div v-if="props[tab.key].total === 0">
                        <Alert>
                            <AlertCircleIcon color="var(--warn)"/>
                            <AlertTitle class="text-warn">No {{tab.key}} found!</AlertTitle>
                            <AlertDescription>
                                <span>You can add {{tab.key}} through a new </span>
                                <Link
                                    :href="sessionsCreate.url()"
                                    class="no-underline! font-medium text-primary hover:underline! hover:text-primary!"
                                >
                                    session
                                </Link>
                            </AlertDescription>
                        </Alert>
                    </div>

                    <div v-else class="grid gap-3">
                        <div
                            v-for="item in props[tab.key].data"
                            :key="item.id"
                            class="rounded-xl border bg-card p-4"
                        >
                            <div class="flex items-start gap-3">
                                <button
                                    type="button"
                                    class="mt-0.5 shrink-0 rounded focus-visible:ring-3 focus-visible:ring-ring/50"
                                    :aria-label="`Star ${item.content_text}`"
                                    :aria-pressed="true"
                                >
                                    <StarIcon
                                        class="size-4"
                                        :class="
                                        1===1
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-muted-foreground'
                                    "
                                    />
                                </button>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium">
                                            {{item.content_text }}
                                        </p>
                                        <Badge
                                            variant="secondary"
                                        >
                                            difficult
                                        </Badge>
                                        <Badge  variant="secondary">
                                            5
                                        </Badge>
                                    </div>

                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{  item.correction_text }}
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground italic"
                                    >
                                        “{{ item.example ?? item.example_html }}”
                                    </p>

                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                    >
                                        <span>{{ item?.practice_question?.practice_session?.topic?.name ?? '' }}</span>
                                        <Link
                                            v-if="item?.practice_question?.practice_session?.topic?.id"
                                            :href="sessionsShow.url( item?.practice_question?.practice_session?.topic?.id  )"
                                            class="font-medium text-primary hover:underline"
                                        >
                                            See original session
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template #loading>
                        <div class="mt-2 w-full flex justify-center items-center"><Spinner class="text-primary size-8"/></div>
                    </template>

                </InfiniteScroll>

            </TabsContent>
        </Tabs>
    </div>
</template>
