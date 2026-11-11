<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpenIcon, PlusIcon } from '@lucide/vue';
import LibrarySearch from '@/components/LibrarySearch.vue';
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
    search: string;
}>();

const cards = ref<NotebookCardData[]>([...props.notebooks]);
const hasMore = ref(props.has_more);
const nextCursor = ref(props.next_cursor);
const loadingMore = ref(false);

const searching = ref(false);
const searchTerm = ref(props.search);

const createOpen = ref(false);
const createName = ref('');
const createTopic = ref('');
const creating = ref(false);
const createError = ref('');

function onSearch(value: string): void {
    searching.value = true;
    router.get(notebooksIndex.url(), value ? { q: value } : {}, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            searching.value = false;
        },
    });
}

async function loadMore(): Promise<void> {
    if (!nextCursor.value || loadingMore.value) {
        return;
    }

    loadingMore.value = true;

    try {
        const params = new URLSearchParams({ cursor: nextCursor.value });

        if (searchTerm.value) {
            params.set('q', searchTerm.value);
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

        <div class="mb-4">
            <LibrarySearch
                v-model="searchTerm"
                :updating="searching"
                label="Search notebooks"
                @search="onSearch"
            />
        </div>

        <p v-if="total > 0" class="mb-3 text-sm text-muted-foreground">
            {{ total }} {{ total === 1 ? 'notebook' : 'notebooks' }}
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
                {{
                    searchTerm
                        ? 'No notebooks match your search.'
                        : 'No notebooks yet.'
                }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    searchTerm
                        ? 'Try different words.'
                        : 'Create a notebook to keep your words, phrases and grammar notes.'
                }}
            </p>
            <Button
                v-if="!searchTerm"
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
            <li v-for="card in cards" :key="card.id">
                <Link
                    :href="notebooksShow.url(card.id)"
                    class="block rounded-lg border border-border bg-card px-4 py-3 transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
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
                    <div class="mt-2 flex gap-3 text-xs text-muted-foreground">
                        <span>{{ card.counts.words }} words</span>
                        <span>{{ card.counts.phrases }} phrases</span>
                        <span>{{ card.counts.grammar_notes }} notes</span>
                    </div>
                </Link>
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
</template>
