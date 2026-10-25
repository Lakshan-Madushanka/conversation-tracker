<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { InfiniteScroll, Link } from '@inertiajs/vue3';
import { StarIcon, AlertCircleIcon } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { show as sessionsShow } from '@/routes/sessions';
import { index as collectionIndex } from '@/routes/collections';
import { create as sessionsCreate } from '@/routes/sessions';
import type { CollectionKey } from '@/lib/sample-data';
import { debounce } from '@/lib/debounce';
import { readCollectionQuery } from '@/lib/collection-query';
import { exampleParts, type ExamplePart } from '@/lib/example';
import { LaravelPaginator, PracticeFeedbackEntry } from '@/types/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    vocabulary: LaravelPaginator<PracticeFeedbackEntry>;
    phrases: LaravelPaginator<PracticeFeedbackEntry>;
    grammar: LaravelPaginator<PracticeFeedbackEntry>;
    mistakes: LaravelPaginator<PracticeFeedbackEntry>;
}>();

// A refresh, the back button or a shared link should open the same view the
// learner left behind, so the tab and its term are read from the url before
// the watcher is registered — seeding must never trigger a visit of its own.
const initial = readCollectionQuery(usePage().url ?? '');

const currentTabKey = ref<CollectionKey>(initial.tab);

const filters = reactive<Record<CollectionKey, { search: string }>>({
    vocabulary: { search: '' },
    phrases: { search: '' },
    grammar: { search: '' },
    mistakes: { search: '' },
});

filters[initial.tab].search = initial.search;

const tabs: { key: CollectionKey; label: string }[] = [
    { key: 'vocabulary', label: 'Vocabulary' },
    { key: 'phrases', label: 'Phrases' },
    { key: 'grammar', label: 'Grammar' },
    { key: 'mistakes', label: 'My mistakes' },
];

watch(filters, () => {
    search();
});

function getCurrentTabLabel() {
    return tabs.find((tab) => currentTabKey.value === tab.key)?.label;
}

const search = debounce(function search() {
    let term = filters[currentTabKey.value].search;

    router.visit(collectionIndex.url(), {
        data: { search: term, forPage: currentTabKey.value },
        only: [currentTabKey.value],
        reset: [currentTabKey.value],
        preserveState: true,
    });
}, 300);

/**
 * The examples of one entry, showing the formatting saved with them. An entry
 * saved before examples were a list keeps its single example in `example`.
 */
function entryExamples(item: PracticeFeedbackEntry): ExamplePart[] {
    const parts = exampleParts(item.example_html);

    return parts.length > 0 ? parts : exampleParts(item.example);
}
</script>

<template>
    <div>
        <Head title="Collections" />

        <PageHeader
            title="Collections"
            description="Vocabulary, phrases, grammar notes and your common mistakes, gathered from every session."
        />

        <Tabs v-model="currentTabKey">
            <TabsList class="grid w-full grid-cols-4">
                <TabsTrigger
                    class="cursor-pointer"
                    v-for="tab in tabs"
                    :key="tab.key"
                    :value="tab.key"
                >
                    {{ tab.label }}
                </TabsTrigger>
            </TabsList>

            <Input
                v-model="filters[currentTabKey].search"
                class="mt-4"
                type="search"
                :placeholder="`Search ${getCurrentTabLabel()}`"
                :aria-label="`Search ${getCurrentTabLabel()}`"
            />

            <TabsContent
                v-for="tab in tabs"
                :key="tab.key"
                :value="tab.key"
                class="mt-4"
            >
                <InfiniteScroll
                    :data="tab.key"
                    :params="{
                        data: {
                            search: filters[tab.key].search,
                            forPage: tab.key,
                        },
                    }"
                >
                    <div v-if="props[tab.key].total === 0">
                        <Alert>
                            <AlertCircleIcon color="var(--warn)" />
                            <AlertTitle class="text-warn"
                                >No {{ tab.key }} found!</AlertTitle
                            >
                            <AlertDescription>
                                <span
                                    >You can add {{ tab.key }} through a new
                                </span>
                                <Link
                                    :href="sessionsCreate.url()"
                                    class="font-medium text-primary no-underline! hover:text-primary! hover:underline!"
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
                                    class="relative mt-0.5 shrink-0 rounded after:absolute after:-inset-1 focus-visible:ring-3 focus-visible:ring-ring/50"
                                    :aria-label="`Star ${item.content_text}`"
                                    :aria-pressed="true"
                                >
                                    <StarIcon
                                        class="size-4"
                                        :class="
                                            1 === 1
                                                ? 'fill-amber-400 text-amber-400'
                                                : 'text-muted-foreground'
                                        "
                                    />
                                </button>

                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p
                                            v-if="
                                                item.type === 'grammar_note' &&
                                                item.content_html
                                            "
                                            class="font-medium"
                                            v-html="item.content_html"
                                        ></p>
                                        <p v-else class="font-medium">
                                            {{ item.content_text }}
                                        </p>
                                        <Badge variant="secondary">
                                            difficult
                                        </Badge>
                                        <Badge variant="secondary"> 5 </Badge>
                                    </div>

                                    <!-- A mistake's correction keeps the
                                        formatting it was saved with; older
                                        plain-text entries fall back to their
                                        words. -->
                                    <p
                                        v-if="
                                            item.type === 'mistake' &&
                                            item.correction_html
                                        "
                                        class="mt-1 text-sm text-muted-foreground"
                                        v-html="item.correction_html"
                                    ></p>
                                    <p
                                        v-else-if="item.correction_text"
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ item.correction_text }}
                                    </p>

                                    <p
                                        v-if="item.reason"
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ item.reason }}
                                    </p>

                                    <p
                                        v-for="(
                                            part, partIndex
                                        ) in entryExamples(item)"
                                        :key="partIndex"
                                        class="mt-1 text-sm text-muted-foreground italic"
                                    >
                                        “<span v-html="part.html"></span>”
                                    </p>

                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                    >
                                        <span>{{
                                            item?.practice_question
                                                ?.practice_session?.topic
                                                ?.name ?? ''
                                        }}</span>
                                        <Link
                                            v-if="
                                                item?.practice_question
                                                    ?.practice_session?.id
                                            "
                                            :href="
                                                sessionsShow.url(
                                                    item.practice_question
                                                        .practice_session.id,
                                                )
                                            "
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
                        <div
                            class="mt-2 flex w-full items-center justify-center"
                        >
                            <Spinner class="size-8 text-primary" />
                        </div>
                    </template>
                </InfiniteScroll>
            </TabsContent>
        </Tabs>
    </div>
</template>
