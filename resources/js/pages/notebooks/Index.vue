<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpenIcon, PlusIcon, Trash2Icon } from '@lucide/vue';
import CollectionMatchHint from '@/components/CollectionMatchHint.vue';
import LibrarySearch from '@/components/LibrarySearch.vue';
import NotebookDeleteDialog from '@/components/NotebookDeleteDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import TopicField from '@/components/TopicField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { jsonRequest } from '@/lib/http';
import {
    index as notebooksIndex,
    show as notebooksShow,
} from '@/routes/notebooks';
import type { NotebookCardData } from '@/types/notebook';
import type { TopicSummary } from '@/types/practice';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    notebooks: NotebookCardData[];
    next_cursor: string | null;
    has_more: boolean;
    total: number;
    topics: TopicSummary[];
    filter_topics: TopicSummary[];
    topic: number | null;
    search: string;
}>();

const cards = ref<NotebookCardData[]>([...props.notebooks]);
const hasMore = ref(props.has_more);
const nextCursor = ref(props.next_cursor);
const loadingMore = ref(false);
const visibleTotal = ref(props.total);

const deletingCard = ref<NotebookCardData | null>(null);
const deleteOpen = ref(false);

const searchBox = ref<InstanceType<typeof LibrarySearch> | null>(null);

/** The card and its count leave together once the server says it is gone. */
function removeCard(notebookId: number): void {
    const index = cards.value.findIndex((card) => card.id === notebookId);

    cards.value = cards.value.filter((card) => card.id !== notebookId);
    visibleTotal.value = Math.max(0, visibleTotal.value - 1);
    deleteOpen.value = false;
    deletingCard.value = null;

    void nextTick(() => focusAfterRemoval(index));
}

/**
 * The removed card's place: the card that took it, the one above, or the
 * search box when the list is now empty.
 */
function focusAfterRemoval(index: number): void {
    const next =
        cards.value[index] ??
        cards.value[index - 1] ??
        cards.value[cards.value.length - 1] ??
        null;

    if (next !== null) {
        document.getElementById(`notebook-link-${next.id}`)?.focus();

        return;
    }

    searchBox.value?.focus();
}

function removeDeleted(notebook: { id: number }): void {
    removeCard(notebook.id);
}

function askDelete(card: NotebookCardData): void {
    deletingCard.value = card;
    deleteOpen.value = true;
}

const searching = ref(false);
const searchTerm = ref(props.search);
const selectedTopic = ref(props.topic === null ? '' : String(props.topic));
const filtering = ref(false);

// A next page that was already on its way when a new list arrived must not
// be appended to it.
let listGeneration = 0;

// Inertia keeps this component alive between visits, so a new page has to
// replace the list the first one brought and not only the address.
watch(
    () => props.notebooks,
    (notebooks) => {
        listGeneration += 1;
        cards.value = [...notebooks];
    },
);

watch(
    () => props.total,
    (total) => {
        visibleTotal.value = total;
    },
);

watch(
    () => [props.next_cursor, props.has_more] as const,
    ([cursor, more]) => {
        nextCursor.value = cursor;
        hasMore.value = more;
    },
);

watch(
    () => props.search,
    (search) => {
        searchTerm.value = search;
    },
);

// A topic that no notebook uses any more comes back as no filter at all.
watch(
    () => props.topic,
    (topic) => {
        selectedTopic.value = topic === null ? '' : String(topic);
    },
);

const createOpen = ref(false);
const createName = ref('');
const createTopic = ref('');
const creating = ref(false);
const createError = ref('');

/** The list's current address: the search and the topic together. */
function visit(): void {
    const params: Record<string, string> = {};

    if (searchTerm.value) {
        params.q = searchTerm.value;
    }

    if (selectedTopic.value) {
        params.topic = selectedTopic.value;
    }

    filtering.value = true;
    router.get(notebooksIndex.url(), params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            filtering.value = false;
            searching.value = false;
        },
    });
}

function onSearch(value: string): void {
    searchTerm.value = value;
    searching.value = true;
    visit();
}

function onTopicChange(value: unknown): void {
    selectedTopic.value =
        value === null || value === undefined ? '' : String(value);
    visit();
}

function clearTopic(): void {
    selectedTopic.value = '';
    visit();
}

async function loadMore(): Promise<void> {
    if (!nextCursor.value || loadingMore.value) {
        return;
    }

    loadingMore.value = true;
    const generation = listGeneration;

    try {
        const params = new URLSearchParams({ cursor: nextCursor.value });

        if (searchTerm.value) {
            params.set('q', searchTerm.value);
        }

        if (selectedTopic.value) {
            params.set('topic', selectedTopic.value);
        }

        const response = await jsonRequest(
            `${notebooksIndex.url()}?${params.toString()}`,
        );

        if (response.ok) {
            const page = (await response.json()) as {
                notebooks: NotebookCardData[];
                next_cursor: string | null;
                has_more: boolean;
            };

            if (generation !== listGeneration) {
                return;
            }

            cards.value = [...cards.value, ...page.notebooks];
            nextCursor.value = page.next_cursor;
            hasMore.value = page.has_more;
        }
    } finally {
        loadingMore.value = false;
    }
}

async function createNotebook(): Promise<void> {
    if (!createName.value.trim()) {
        createError.value = 'Give the notebook a name.';

        return;
    }

    creating.value = true;
    createError.value = '';

    router.post(
        notebooksIndex.url(),
        {
            name: createName.value.trim(),
            topic: createTopic.value.trim() || undefined,
        },
        {
            onError: (errors) => {
                createError.value =
                    errors.name ?? 'Could not create the notebook.';
                creating.value = false;
            },
            onFinish: () => {
                creating.value = false;
            },
        },
    );
}

function openCreate(): void {
    createName.value = '';
    createTopic.value = '';
    createError.value = '';
    createOpen.value = true;
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

/**
 * A card opens its notebook at the top. When the search matched a saved row
 * instead of the card's own words, it opens the board at that row, using the
 * same address a Collections item uses.
 */
function cardHref(card: NotebookCardData): string {
    const match = card.match;

    if (match === null) {
        return notebooksShow.url(card.id);
    }

    const query: Record<string, string> = {
        field: 'entry',
        type: match.type,
        position: String(match.position),
    };

    if (match.entry !== null && match.entry !== '') {
        query.entry = match.entry;
    }

    return notebooksShow.url(card.id, { query });
}
</script>

<template>
    <Head title="Notebooks" />

    <div class="mx-auto w-full max-w-3xl px-4 py-6">
        <PageHeader title="Notebooks">
            <template #actions>
                <Button type="button" size="sm" @click="openCreate">
                    <PlusIcon aria-hidden="true" />
                    New notebook
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
                <LibrarySearch
                    ref="searchBox"
                    v-model="searchTerm"
                    :updating="searching"
                    label="Search notebooks"
                    @search="onSearch"
                />
            </div>
            <NativeSelect
                v-if="filter_topics.length > 0"
                id="notebook-topic-filter"
                :model-value="selectedTopic"
                class="w-full sm:w-48"
                aria-label="Filter by topic"
                :disabled="filtering"
                @update:model-value="onTopicChange"
            >
                <NativeSelectOption value="">All topics</NativeSelectOption>
                <NativeSelectOption
                    v-for="option in filter_topics"
                    :key="option.id"
                    :value="String(option.id)"
                >
                    {{ option.name }}
                </NativeSelectOption>
            </NativeSelect>
        </div>

        <p v-if="visibleTotal > 0" class="mb-3 text-sm text-muted-foreground">
            {{ visibleTotal }}
            {{ visibleTotal === 1 ? 'notebook' : 'notebooks' }}
        </p>

        <!-- Empty state -->
        <div
            v-if="cards.length === 0"
            class="rounded-lg border border-dashed border-border px-6 py-12 text-center"
        >
            <BookOpenIcon
                class="mx-auto mb-3 size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <p class="font-medium">
                <template v-if="searchTerm">
                    No notebooks match your search.
                </template>
                <template v-else-if="selectedTopic">
                    No notebooks in this topic.
                </template>
                <template v-else> No notebooks yet. </template>
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                <template v-if="searchTerm">
                    Try different words{{
                        selectedTopic ? ' or another topic' : ''
                    }}.
                </template>
                <template v-else-if="selectedTopic">
                    Choose another topic or show them all.
                </template>
                <template v-else>
                    Create a notebook to keep your words, phrases and grammar
                    notes.
                </template>
            </p>
            <Button
                v-if="selectedTopic"
                type="button"
                variant="outline"
                class="mt-4"
                @click="clearTopic"
            >
                Show all topics
            </Button>
            <Button
                v-else-if="!searchTerm"
                type="button"
                class="mt-4"
                @click="openCreate"
            >
                <PlusIcon aria-hidden="true" />
                New notebook
            </Button>
        </div>

        <!-- Card list -->
        <ul v-else class="space-y-3" role="list">
            <li v-for="card in cards" :key="card.id" class="relative">
                <Link
                    :id="`notebook-link-${card.id}`"
                    :href="cardHref(card)"
                    class="block rounded-lg border border-border bg-card px-4 py-3 pr-14 transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="leading-snug font-medium">
                            {{ card.name }}
                        </h2>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ formatDate(card.last_updated_at) }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ card.topic }}
                    </p>
                    <p
                        v-if="card.description"
                        class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ card.description }}
                    </p>
                    <CollectionMatchHint
                        v-if="card.match"
                        :match="card.match"
                    />
                    <div class="mt-2 flex gap-3 text-xs text-muted-foreground">
                        <span>{{ card.counts.words }} words</span>
                        <span>{{ card.counts.phrases }} phrases</span>
                        <span>{{ card.counts.grammar_notes }} notes</span>
                    </div>
                </Link>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="absolute top-2 right-2 min-h-11 text-muted-foreground hover:text-destructive md:min-h-9"
                    @click="askDelete(card)"
                >
                    <Trash2Icon aria-hidden="true" />
                    Delete
                    <span class="sr-only"> {{ card.name }}</span>
                </Button>
            </li>
        </ul>

        <!-- Load more -->
        <div v-if="hasMore" class="mt-4 text-center">
            <Button
                type="button"
                variant="outline"
                :disabled="loadingMore"
                @click="loadMore"
            >
                {{ loadingMore ? 'Loading…' : 'Load more' }}
            </Button>
        </div>
    </div>

    <!-- Create dialog -->
    <Dialog v-model:open="createOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>New notebook</DialogTitle>
                <DialogDescription>
                    Give it a name and an optional topic. You can add words,
                    phrases and grammar notes on the next screen.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="createNotebook">
                <div>
                    <label
                        for="create-name"
                        class="mb-1 block text-sm font-medium"
                    >
                        Name
                    </label>
                    <Input
                        id="create-name"
                        v-model="createName"
                        placeholder="e.g. Kitchen vocabulary"
                        maxlength="120"
                        autofocus
                    />
                </div>
                <TopicField v-model="createTopic" :suggestions="topics" />
                <p v-if="createError" class="text-sm text-destructive">
                    {{ createError }}
                </p>
                <DialogFooter>
                    <Button type="submit" :disabled="creating">
                        {{ creating ? 'Creating…' : 'Create notebook' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <NotebookDeleteDialog
        v-if="deletingCard !== null"
        v-model:open="deleteOpen"
        :notebook-id="deletingCard.id"
        :notebook-name="deletingCard.name"
        :notebook-topic="deletingCard.topic"
        @deleted="removeDeleted"
        @missing="removeCard"
    />
</template>
